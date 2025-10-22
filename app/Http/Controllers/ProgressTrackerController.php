<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressTrackerController extends Controller
{
    public function index(): Response
    {
        $query = Application::query()
            ->with(['status', 'gatewayIntegration', 'account']);

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

        $applications = $query
            ->latest()
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

        // Calculate stats from all applications (not filtered)
        $allApplications = Application::with('status')->get();
        $stats = [
            'total_applications' => $allApplications->count(),
            'pending_contracts' => $allApplications->filter(fn($app) => $app->status?->current_step === 'application_sent')->count(),
            'awaiting_approval' => $allApplications->filter(fn($app) => $app->status?->current_step === 'contract_submitted')->count(),
            'awaiting_payment' => $allApplications->filter(fn($app) => $app->status?->current_step === 'invoice_sent')->count(),
            'in_integration' => $allApplications->filter(fn($app) => in_array($app->status?->current_step, ['invoice_paid', 'gateway_integrated']))->count(),
            'live_accounts' => $allApplications->filter(fn($app) => $app->status?->current_step === 'account_live')->count(),
        ];

        // Available status options for filter dropdown
        $statusOptions = [
            'created' => 'Created',
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