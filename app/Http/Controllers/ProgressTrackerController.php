<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Account;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressTrackerController extends Controller
{
    public function index(): Response
    {
        $query = Application::query()
            ->with(['status', 'gatewayIntegration', 'account']);

        // Access control based on authentication guard
        if (auth()->guard('account')->check()) {
            // Accounts can only see their own applications
            $query->where('account_id', auth()->guard('account')->id());
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            if ($user->isAdmin()) {
                // Admins see all applications
                // No filtering needed
            } else {
                // Regular users see only applications from accounts they created
                $userAccountIds = Account::where('user_id', $user->id)->pluck('id');
                $query->whereIn('account_id', $userAccountIds);
            }
        } else {
            abort(403, 'Unauthorized access.');
        }

        // Search filter
        if ($search = Request::input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('trading_name', 'like', "%{$search}%");
            });
        }

        // Account name filter
        if ($accountSearch = Request::input('account_search')) {
            $query->whereHas('account', function ($q) use ($accountSearch) {
                $q->where('name', 'like', "%{$accountSearch}%");
            });
        }

        // Status filter (multi-select support)
        if ($statuses = Request::input('status')) {
            if (is_array($statuses)) {
                $query->whereHas('status', function ($q) use ($statuses) {
                    $q->whereIn('current_step', $statuses);
                });
            } else {
                $query->whereHas('status', function ($q) use ($statuses) {
                    $q->where('current_step', $statuses);
                });
            }
        }

        // Date range filter for last updated
        if ($dateFrom = Request::input('date_from')) {
            $query->where('updated_at', '>=', $dateFrom);
        }
        if ($dateTo = Request::input('date_to')) {
            $query->where('updated_at', '<=', $dateTo . ' 23:59:59');
        }

        // Calculate progress based on completed steps
        $applications = $query
            ->leftJoin('application_statuses', 'applications.id', '=', 'application_statuses.application_id')
            ->select('applications.*')
            ->selectRaw('
                CASE 
                    WHEN application_statuses.account_live_at IS NOT NULL THEN 100
                    WHEN application_statuses.gateway_integrated_at IS NOT NULL THEN 95
                    WHEN application_statuses.invoice_paid_at IS NOT NULL THEN 90
                    WHEN application_statuses.invoice_sent_at IS NOT NULL THEN 82
                    WHEN application_statuses.application_approved_at IS NOT NULL THEN 70
                    WHEN application_statuses.contract_submitted_at IS NOT NULL THEN 60
                    WHEN application_statuses.contract_signed_at IS NOT NULL THEN 50
                    WHEN application_statuses.documents_approved_at IS NOT NULL THEN 30
                    WHEN application_statuses.documents_uploaded_at IS NOT NULL THEN 20
                    WHEN application_statuses.contract_sent_at IS NOT NULL THEN 10
                    ELSE 0
                END as calculated_progress
            ')
            ->orderBy('calculated_progress', 'desc') // Furthest progress first
            ->orderBy('applications.updated_at', 'desc') // Then most recently updated
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($app) => [
                'id' => $app->id,
                'name' => $app->name,
                'trading_name' => $app->trading_name,
                'account_name' => $app->account?->name,
                'current_step' => $app->status?->current_step ?? 'created',
                'progress_percentage' => $app->status?->progress_percentage ?? 0,
                'gateway_provider' => $app->gatewayIntegration?->gateway_provider,
                'requires_attention' => $app->status?->requires_additional_info ?? false,
                'updated_at' => $app->updated_at->format('Y-m-d H:i'),
            ]);

        // Calculate stats based on what user can see (filtered applications)
        // Get the base query again with same filters for stats
        $statsQuery = Application::query()->with('status');
        
        // Apply same access control for stats
        if (auth()->guard('account')->check()) {
            $statsQuery->where('account_id', auth()->guard('account')->id());
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            if (!$user->isAdmin()) {
                $userAccountIds = Account::where('user_id', $user->id)->pluck('id');
                $statsQuery->whereIn('account_id', $userAccountIds);
            }
        }
        
        $allApplications = $statsQuery->get();
        
        $stats = [
            'total_applications' => $allApplications->count(),
            
            // Awaiting Documents: Has contract_sent timestamp but NOT both contract_signed AND documents_approved
            'awaiting_documents' => $allApplications->filter(function($app) {
                $timestamps = [
                    'documents_uploaded' => $app->status?->documents_uploaded_at,
                    'contract_sent' => $app->status?->contract_sent_at,
                    'contract_signed' => $app->status?->contract_signed_at,
                    'documents_approved' => $app->status?->documents_approved_at,
                ];
                
                // Has contract_sent OR documents_uploaded timestamp
                $hasEarlyTimestamp = $timestamps['contract_sent'] || $timestamps['documents_uploaded'];
                
                // Does NOT have both contract_signed AND documents_approved
                $hasNotCompleted = !($timestamps['contract_signed'] && $timestamps['documents_approved']);
                
                return $hasEarlyTimestamp && $hasNotCompleted;
            })->count(),
            
            // Contract Sent: Has contract_sent_at but NOT contract_signed_at
            'contract_sent' => $allApplications->filter(function($app) {
                return $app->status?->contract_sent_at && !$app->status?->contract_signed_at;
            })->count(),
            
            // Contract Signed: Has contract_signed_at but NOT application_approved_at
            'contract_signed' => $allApplications->filter(function($app) {
                return $app->status?->contract_signed_at && !$app->status?->application_approved_at;
            })->count(),
            
            // Application Approved: Has application_approved_at but NOT invoice_paid_at
            'application_approved' => $allApplications->filter(function($app) {
                return $app->status?->application_approved_at && !$app->status?->invoice_paid_at;
            })->count(),
            
            // Awaiting Payment: Has invoice_sent_at but NOT invoice_paid_at
            'awaiting_payment' => $allApplications->filter(function($app) {
                return $app->status?->invoice_sent_at && !$app->status?->invoice_paid_at;
            })->count(),
        ];

        // Available status options for filter dropdown
        $statusOptions = [
            'created' => 'Created',
            'contract_sent' => 'Contract Sent',
            'documents_uploaded' => 'Documents Uploaded',
            'documents_approved' => 'Documents Approved',
            'contract_signed' => 'Contract Signed',
            'application_sent' => 'Contract Sent',
            'contract_completed' => 'Contract Signed',
            'contract_submitted' => 'Submitted',
            'application_approved' => 'Approved',
            'approval_email_sent' => 'Approval Sent',
            'invoice_sent' => 'Invoice Sent',
            'invoice_paid' => 'Payment Received',
            'gateway_integrated' => 'Integration Complete',
            'account_live' => 'Live',
        ];

        return Inertia::render('ProgressTracker/Index', [
            'applications' => $applications,
            'stats' => $stats,
            'filters' => [
                'search' => Request::input('search'),
                'account_search' => Request::input('account_search'),
                'status' => (array) Request::input('status'),
                'date_from' => Request::input('date_from'),
                'date_to' => Request::input('date_to'),
            ],
            'statusOptions' => $statusOptions,
        ]);        
    }
}