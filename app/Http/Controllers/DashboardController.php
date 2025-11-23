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
        $dateFrom = !empty($filters['date_from'])
            ? \Carbon\Carbon::parse($filters['date_from'])->startOfDay() 
            : now()->subDays(30)->startOfDay();
        $dateTo = !empty($filters['date_to'])
            ? \Carbon\Carbon::parse($filters['date_to'])->endOfDay() 
            : now()->endOfDay();

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
                $status = $filters['status'];
                
                // Map status to its timestamp column
                $timestampMapping = [
                    'created' => null,
                    'contract_sent' => 'contract_sent_at',
                    'documents_uploaded' => 'documents_uploaded_at',
                    'documents_approved' => 'documents_approved_at',
                    'contract_signed' => 'contract_signed_at',
                    'contract_submitted' => 'contract_submitted_at',
                    'application_approved' => 'application_approved_at',
                    'invoice_sent' => 'invoice_sent_at',
                    'invoice_paid' => 'invoice_paid_at',
                    'gateway_integrated' => 'gateway_integrated_at',
                    'account_live' => 'account_live_at',
                ];
                
                $timestampColumn = $timestampMapping[$status] ?? null;
                
                $q->where(function ($query) use ($status, $timestampColumn) {
                    // Current step matches
                    $query->where('current_step', $status);
                    
                    // OR has a timestamp for this status (meaning it reached it at some point)
                    if ($timestampColumn) {
                        $query->orWhereNotNull($timestampColumn);
                    }
                });
            });
        }
        
        // Apply global date filters if set
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            // Convert to Carbon instances and set time boundaries
            $dateFrom = \Carbon\Carbon::parse($filters['date_from'])->startOfDay();
            $dateTo = \Carbon\Carbon::parse($filters['date_to'])->endOfDay();
            
            $applicationsQuery->whereBetween('applications.created_at', [$dateFrom, $dateTo]);
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
                // Map status to its timestamp column
                $timestampMapping = [
                    'created' => null, // Always count all applications
                    'documents_uploaded' => 'documents_uploaded_at',
                    'documents_approved' => 'documents_approved_at',
                    'application_sent' => 'contract_sent_at', // Contract sent
                    'contract_completed' => 'contract_signed_at', // Contract signed
                    'contract_submitted' => 'contract_submitted_at',
                    'application_approved' => 'application_approved_at',
                    'invoice_sent' => 'invoice_sent_at',
                    'invoice_paid' => 'invoice_paid_at',
                    'gateway_integrated' => 'gateway_integrated_at',
                    'account_live' => 'account_live_at',
                ];
                
                $timestampColumn = $timestampMapping[$status] ?? null;
                
                // Count applications that have reached this status
                $count = ApplicationStatus::query()
                    ->whereIn('application_id', $applicationIds)
                    ->where(function ($q) use ($status, $timestampColumn) {
                        // Current step matches
                        $q->where('current_step', $status);
                        
                        // OR has a timestamp for this status (meaning it reached it at some point)
                        if ($timestampColumn) {
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
        
        // Get the most recent application timestamp (for "Last Application" metric)
        $mostRecentApplication = (clone $applicationsQuery)
            ->orderBy('applications.created_at', 'desc')
            ->first();

        // Average scaling fees
        $avgSetupFee = (clone $applicationsQuery)->avg('scaling_fee') ?? 0;
        $totalSetupFees = (clone $applicationsQuery)->sum('scaling_fee') ?? 0;

        // Top 5 accounts by application count
        $topAccounts = (clone $applicationsQuery)
            ->select('account_id', DB::raw('SUM(scaling_fee) as total_fees'), DB::raw('count(*) as app_count'))
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
                'scaling_fee' => $app->scaling_fee,
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
            'contract_sent' => 'Contract Sent',
            'documents_uploaded' => 'Documents Uploaded',
            'documents_approved' => 'Documents Approved',
            'contract_signed' => 'Contract Signed',
            'contract_submitted' => 'Contract Submitted',
            'application_approved' => 'Application Approved',
            'invoice_sent' => 'Invoice Sent',
            'invoice_paid' => 'Invoice Paid',
            'gateway_integrated' => 'Gateway Integrated',
            'account_live' => 'Account Live',
        ];

        // Calculate processing times for applications
        $processingData = $this->getProcessingData($applicationsQuery);

        $userAccountData = [];
        if ($isAdmin) {
            $users = User::withCount('accounts')->get();
            $userAccountData = $users->map(function($user) {
                return [
                    'name' => $user->name,
                    'account_count' => $user->accounts_count,
                ];
            })->toArray();
        }

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
                'mostRecentApplicationDate' => $mostRecentApplication?->created_at?->toISOString(),
            ],
            'charts' => [
                'statusCounts' => $statusCounts,
                'applicationsOverTime' => $applicationsOverTime,
                'topAccounts' => $topAccounts,
                'userAccountData' => $userAccountData,
            ],
            'recentApplications' => $recentApplications,
            'processingStats' => $processingData['stats'],
            'processingApplications' => $processingData['applications'],
        ]);
    }

    /**
     * Calculate processing times for applications
     */
    private function getProcessingData($applicationsQuery)
    {
        $page = request()->get('processing_page', 1);
        $perPage = 10;

        // Get all applications with their status timestamps
        $applications = (clone $applicationsQuery)
            ->with(['account', 'status'])
            ->get()
            ->map(function ($app) {
                $status = $app->status;
                if (!$status) {
                    return null;
                }

                // Skip applications that are still in 'created' status
                if ($status->current_step === 'created') {
                    return null;
                }

                // Calculate days from creation to current status
                $createdAt = $app->created_at;
                $currentTimestamp = null;
                
                // Get the most recent timestamp based on current step
                switch ($status->current_step) {
                    case 'documents_uploaded':
                        $currentTimestamp = $status->documents_uploaded_at;
                        break;
                    case 'documents_approved':
                        $currentTimestamp = $status->documents_approved_at;
                        break;
                    case 'contract_sent':
                        $currentTimestamp = $status->contract_sent_at;
                        break;
                    case 'contract_signed':
                        $currentTimestamp = $status->contract_signed_at;
                        break;
                    case 'contract_submitted':
                        $currentTimestamp = $status->contract_submitted_at;
                        break;
                    case 'application_approved':
                        $currentTimestamp = $status->application_approved_at;
                        break;
                    case 'invoice_sent':
                        $currentTimestamp = $status->invoice_sent_at;
                        break;
                    case 'invoice_paid':
                        $currentTimestamp = $status->invoice_paid_at;
                        break;
                    case 'gateway_integrated':
                        $currentTimestamp = $status->gateway_integrated_at;
                        break;
                    case 'account_live':
                        $currentTimestamp = $status->account_live_at;
                        break;
                    default:
                        // If no timestamp found, skip this application
                        return null;
                }

                // Skip if we don't have a valid timestamp
                if (!$currentTimestamp) {
                    return null;
                }

                // If account_live, use that as end time, otherwise use current timestamp
                $endTimestamp = $status->account_live_at ?? $currentTimestamp;
                $daysInProcess = $createdAt->diffInDays($endTimestamp);

                return [
                    'id' => $app->id,
                    'name' => $app->name,
                    'account_name' => $app->account?->name ?? 'Unknown',
                    'account_id' => $app->account_id,
                    'current_step' => $status->current_step,
                    'created_at' => $createdAt->format('Y-m-d H:i'),
                    'days_in_process' => $daysInProcess,
                    'is_completed' => $status->current_step === 'account_live',
                    'completion_date' => $status->account_live_at?->format('Y-m-d H:i'),
                ];
            })
            ->filter() // Remove nulls (created status and apps without timestamps)
            ->sortBy('days_in_process')
            ->values();

        // Calculate statistics
        $completedApplications = $applications->where('is_completed', true);
        
        $stats = [
            'fastest' => $completedApplications->min('days_in_process') ?? 0,
            'slowest' => $completedApplications->max('days_in_process') ?? 0,
            'average' => $completedApplications->avg('days_in_process') 
                ? round($completedApplications->avg('days_in_process'), 1) 
                : 0,
            'median' => $this->calculateMedian($completedApplications->pluck('days_in_process')->toArray()),
            'total_completed' => $completedApplications->count(),
            'total_in_progress' => $applications->where('is_completed', false)->count(),
        ];

        // Paginate results
        $total = $applications->count();
        $lastPage = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        
        $paginatedApplications = $applications->slice($offset, $perPage)->values();

        return [
            'stats' => $stats,
            'applications' => [
                'data' => $paginatedApplications,
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * Calculate median value
     */
    private function calculateMedian(array $values)
    {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return round(($values[$middle - 1] + $values[$middle]) / 2, 1);
        }
        
        return round($values[$middle], 1);
    }
}