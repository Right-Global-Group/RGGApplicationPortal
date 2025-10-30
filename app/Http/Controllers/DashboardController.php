<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // Determine user role and access
        $isAccount = auth()->guard('account')->check();
        $isAdmin = false;
        $accountId = null;

        if ($isAccount) {
            $accountId = auth()->guard('account')->id();
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            $isAdmin = $user->isAdmin();
        }

        // Get filters
        $filters = $request->only(['date_from', 'date_to', 'account_id', 'status', 'search']);
        
        // Date range (default: last 30 days) - for "created over time" chart only
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        // Build base query for ALL applications (not filtered by date)
        $applicationsQuery = Application::query()
            ->with(['account', 'status']);

        // Build query for accounts
        $accountsQuery = Account::query()
            ->with(['applications']);

        // Role-based filtering
        if ($isAccount) {
            // Accounts only see their own data
            $applicationsQuery->where('account_id', $accountId);
            $accountsQuery->where('id', $accountId);
        } elseif (!$isAdmin) {
            // Non-admin users see only their created accounts
            $user = auth()->guard('web')->user();
            $userAccountIds = Account::where('user_id', $user->id)->pluck('id');
            $applicationsQuery->whereIn('account_id', $userAccountIds);
            $accountsQuery->whereIn('id', $userAccountIds);
        }

        // Apply additional filters
        if (!empty($filters['account_id'])) {
            $applicationsQuery->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['status'])) {
            $applicationsQuery->whereHas('status', function ($q) use ($filters) {
                $q->where('current_step', $filters['status']);
            });
        }
        
        // Apply global date filters if set
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $applicationsQuery->whereBetween('applications.created_at', [$filters['date_from'], $filters['date_to']]);
        }

        if (!empty($filters['search'])) {
            $applicationsQuery->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('trading_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        // === METRICS ===

        // Overall counts
        $totalApplications = (clone $applicationsQuery)->count();
        $totalAccounts = (clone $accountsQuery)->count();
        $totalUsers = $isAdmin ? User::count() : 0;

        // Applications by status - count apps that have reached each status
        $allStatuses = [
            'created',
            'documents_uploaded',
            'documents_approved',
            'application_sent',
            'contract_completed',
            'contract_submitted',
            'application_approved',
            'invoice_sent',
            'invoice_paid',
            'gateway_integrated',
            'account_live',
        ];

        $statusCounts = [];
        $applicationIds = (clone $applicationsQuery)->pluck('id')->toArray();
        
        if (!empty($applicationIds)) {
            foreach ($allStatuses as $status) {
                // Count applications currently at this status OR with a timestamp for this status
                $count = ApplicationStatus::query()
                    ->whereIn('application_id', $applicationIds)
                    ->where(function ($q) use ($status) {
                        // Current step matches
                        $q->where('current_step', $status);
                        
                        // OR has a timestamp for this status (meaning it reached it at some point)
                        $timestampColumn = $status . '_at';
                        if (in_array($timestampColumn, [
                            'documents_uploaded_at',
                            'documents_approved_at',
                            'contract_sent_at',
                            'contract_completed_at',
                            'application_approved_at',
                            'invoice_sent_at',
                            'invoice_paid_at',
                            'account_live_at',
                        ])) {
                            $q->orWhereNotNull($timestampColumn);
                        }
                    })
                    ->distinct('application_id')
                    ->count('application_id');
                    
                $statusCounts[$status] = $count;
            }
        } else {
            // No applications, set all counts to 0
            foreach ($allStatuses as $status) {
                $statusCounts[$status] = 0;
            }
        }

        // Applications created over time (for line chart) - THIS uses date filter
        $applicationsOverTimeQuery = (clone $applicationsQuery)
            ->whereBetween('applications.created_at', [$dateFrom, $dateTo]);
            
        $applicationsOverTime = $applicationsOverTimeQuery
            ->select(DB::raw('DATE(applications.created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => $item->count,
            ]);

        // Average setup fees
        $avgSetupFee = (clone $applicationsQuery)->avg('setup_fee') ?? 0;
        $totalSetupFees = (clone $applicationsQuery)->sum('setup_fee') ?? 0;

        // Top 5 accounts by application count
        $topAccounts = (clone $applicationsQuery)
            ->select('account_id', DB::raw('SUM(setup_fee) as total_fees'), DB::raw('count(*) as app_count'))
            ->groupBy('account_id')
            ->orderBy('total_fees', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $account = Account::find($item->account_id);
                return [
                    'name' => $account?->name ?? 'Unknown',
                    'total_fees' => round($item->total_fees, 2),
                    'app_count' => $item->app_count,
                ];
            });

        // Recent applications (last 10)
        $recentApplications = (clone $applicationsQuery)
            ->with(['account', 'status'])
            ->orderBy('applications.created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($app) => [
                'id' => $app->id,
                'name' => $app->name,
                'account_name' => $app->account?->name,
                'status' => $app->status?->current_step ?? 'created',
                'created_at' => $app->created_at->format('Y-m-d H:i'),
            ]);

        // Available accounts for filter (role-based)
        $availableAccounts = [];
        if (!$isAccount) {
            $availableAccountsQuery = Account::query();
            if (!$isAdmin) {
                $availableAccountsQuery->where('user_id', auth()->id());
            }
            $availableAccounts = $availableAccountsQuery
                ->orderBy('name')
                ->get()
                ->map->only('id', 'name');
        }

        // All possible statuses for filter
        $availableStatuses = [
            'created' => 'Application Created',
            'documents_uploaded' => 'Documents Uploaded',
            'documents_approved' => 'Documents Approved',
            'application_sent' => 'Contract Sent',
            'contract_completed' => 'Contract Signed',
            'contract_submitted' => 'Contract Submitted',
            'application_approved' => 'Application Approved',
            'invoice_sent' => 'Invoice Sent',
            'invoice_paid' => 'Payment Received',
            'gateway_integrated' => 'Gateway Integration',
            'account_live' => 'Account Live',
        ];

        return Inertia::render('Dashboard/Index', [
            'filters' => $filters + ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'isAccount' => $isAccount,
            'isAdmin' => $isAdmin,
            'availableAccounts' => $availableAccounts,
            'availableStatuses' => $availableStatuses,
            'metrics' => [
                'totalApplications' => $totalApplications,
                'totalAccounts' => $totalAccounts,
                'totalUsers' => $totalUsers,
                'avgSetupFee' => round($avgSetupFee, 2),
                'totalSetupFees' => round($totalSetupFees, 2),
            ],
            'charts' => [
                'statusCounts' => $statusCounts,
                'applicationsOverTime' => $applicationsOverTime,
                'topAccounts' => $topAccounts,
            ],
            'recentApplications' => $recentApplications,
        ]);
    }
}