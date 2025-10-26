<?php

namespace App\Http\Controllers;

use App\Events\ApplicationCreatedEvent;
use App\Events\FeesChangedEvent;
use App\Models\ApplicationDocument;
use App\Models\Application;
use App\Models\Account;
use App\Models\EmailReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationsController extends Controller
{
    public function index(): Response
    {
        $query = Application::query()
            ->with(['account', 'user'])
            ->orderBy('id', 'desc');

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

        // Search filter for application name
        if ($search = Request::input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Search filter for account name
        if ($accountSearch = Request::input('account_search')) {
            $query->whereHas('account', function ($q) use ($accountSearch) {
                $q->where('name', 'like', "%{$accountSearch}%");
            });
        }

        // Date range filter
        if ($dateFrom = Request::input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = Request::input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Deleted filter
        if ($deleted = Request::input('deleted')) {
            if ($deleted === 'with') {
                $query->withTrashed();
            } elseif ($deleted === 'only') {
                $query->onlyTrashed();
            }
        }

        $applications = $query
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($application) => [
                'id' => $application->id,
                'account_id' => $application->account_id,
                'account_name' => $application->account?->name,
                'name' => $application->name,
                'user_name' => $application->user
                    ? ($application->user->first_name . ' ' . $application->user->last_name)
                    : null,
                'deleted_at' => $application->deleted_at,
                'created_at' => $application->created_at?->format('Y-m-d H:i'),
                'updated_at' => $application->updated_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Applications/Index', [
            'filters' => Request::only(['search', 'account_search', 'date_from', 'date_to', 'deleted']),
            'applications' => $applications,
        ]);
    }

    public function create(): Response
    {
        $accountId = Request::query('account_id');
        
        // Get available accounts based on user role
        if (auth()->guard('account')->check()) {
            $accounts = Account::where('id', auth()->guard('account')->id())
                ->orderBy('name')
                ->get()
                ->map->only('id', 'name');
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            if ($user->isAdmin()) {
                // Admins can create applications for any account
                $accounts = Account::orderBy('name')
                    ->get()
                    ->map->only('id', 'name');
            } else {
                // Regular users can only create applications for accounts they created
                $accounts = Account::where('user_id', $user->id)
                    ->orderBy('name')
                    ->get()
                    ->map->only('id', 'name');
            }
        } else {
            abort(403, 'Unauthorized access.');
        }

        return Inertia::render('Applications/Create', [
            'accounts' => $accounts,
            'preselected_account_id' => $accountId ? (int) $accountId : null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $validated = Request::validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')],
            'name' => ['required', 'max:100'],
            'email' => ['nullable', 'max:50', 'email'],
            'phone' => ['nullable', 'max:50'],
            'address' => ['nullable', 'max:150'],
            'city' => ['nullable', 'max:50'],
            'region' => ['nullable', 'max:50'],
            'country' => ['nullable', 'max:2'],
            'postal_code' => ['nullable', 'max:25'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
            'transaction_percentage' => ['required', 'numeric', 'min:0'],
            'transaction_fixed_fee' => ['required', 'numeric', 'min:0'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'monthly_minimum' => ['required', 'numeric', 'min:0'],
            'service_fee' => ['required', 'numeric', 'min:0'],
        ]);

        // Verify user has permission to create application for this account
        $account = Account::findOrFail($validated['account_id']);
        
        if (auth()->guard('account')->check()) {
            // Accounts can only create for themselves
            if ($account->id !== auth()->guard('account')->id()) {
                abort(403, 'You can only create applications for your own account.');
            }
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            if (!$user->isAdmin() && $account->user_id !== $user->id) {
                abort(403, 'You can only create applications for accounts you created.');
            }
        }

        $application = Application::create(array_merge(
            $validated,
            ['user_id' => auth()->id()]
        ));

        // Fire event to send email notification to account
        event(new ApplicationCreatedEvent($application));

        return Redirect::route('applications.status', $application)->with('success', 'Application created.');
    }

    public function edit(Application $application): Response
    {
        // Check permissions
        if (auth()->guard('account')->check()) {
            if ($application->account_id !== auth()->guard('account')->id()) {
                abort(403);
            }
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
                abort(403, 'You can only edit applications for accounts you created.');
            }
        }
    
        // Load relationships including parent application and status
        $application->load(['account', 'user', 'parentApplication', 'status']);
    
        // Determine if current user can change fees (admin only)
        $canChangeFees = auth()->guard('web')->check() && auth()->guard('web')->user()->isAdmin();
    
        return Inertia::render('Applications/Edit', [
            'application' => [
                'id' => $application->id,
                'account_id' => $application->account_id,
                'name' => $application->name,
                'account_name' => $application->account?->name,
                'user_id' => $application->user_id,
                'user_name' => $application->user
                    ? ($application->user->first_name . ' ' . $application->user->last_name)
                    : null,
                'parent_application_id' => $application->parent_application_id,
                'parent_application_name' => $application->parentApplication?->name,
                'setup_fee' => $application->setup_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'monthly_fee' => $application->monthly_fee,
                'monthly_minimum' => $application->monthly_minimum,
                'service_fee' => $application->service_fee,
                'fees_confirmed' => $application->fees_confirmed,
                'fees_confirmed_at' => $application->fees_confirmed_at?->format('Y-m-d H:i'),
                'deleted_at' => $application->deleted_at,
                'created_at' => $application->created_at,
                'updated_at' => $application->updated_at,
                // ADD THIS - Include status information
                'status' => $application->status ? [
                    'current_step' => $application->status->current_step,
                    'progress_percentage' => $application->status->progress_percentage,
                ] : null,
            ],
            'accounts' => Account::orderBy('name')->get()->map->only('id', 'name'),
            'canChangeFees' => $canChangeFees,
            'documents' => $application->documents()->get()->map(fn ($doc) => [
                'id' => $doc->id,
                'document_category' => $doc->document_category,
                'original_filename' => $doc->original_filename,
                'uploaded_at' => $doc->created_at?->format('Y-m-d H:i'),
            ]),
            'documentCategories' => ApplicationDocument::getRequiredCategories(),
            'categoryDescriptions' => collect(ApplicationDocument::getRequiredCategories())
                ->mapWithKeys(fn ($label, $key) => [$key => ApplicationDocument::getCategoryDescription($key)])
                ->toArray(),
        ]);
    }
    
    public function update(Application $application): RedirectResponse
    {
        // Check permissions
        if (auth()->guard('account')->check()) {
            if ($application->account_id !== auth()->guard('account')->id()) {
                abort(403);
            }
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
                abort(403, 'You can only update applications for accounts you created.');
            }
        }

        $application->update(
            Request::validate([
                'account_id' => ['required', Rule::exists('accounts', 'id')],
                'name' => ['required', 'max:100'],
                'email' => ['nullable', 'max:50', 'email'],
                'phone' => ['nullable', 'max:50'],
                'address' => ['nullable', 'max:150'],
                'city' => ['nullable', 'max:50'],
                'region' => ['nullable', 'max:50'],
                'country' => ['nullable', 'max:2'],
                'postal_code' => ['nullable', 'max:25'],
            ])
        );

        return Redirect::back()->with('success', 'Application updated.');
    }

    public function changeFees(Application $application): RedirectResponse
    {
        // Check permissions - only admins can change fees
        if (!auth()->guard('web')->check() || !auth()->guard('web')->user()->isAdmin()) {
            abort(403, 'Only administrators can change application fees.');
        }

        $validated = Request::validate([
            'name' => ['required', 'max:100'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
            'transaction_percentage' => ['required', 'numeric', 'min:0'],
            'transaction_fixed_fee' => ['required', 'numeric', 'min:0'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'monthly_minimum' => ['required', 'numeric', 'min:0'],
            'service_fee' => ['required', 'numeric', 'min:0'],
        ]);

        // Create new application with updated fees
        $newApplication = Application::create([
            'account_id' => $application->account_id,
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'parent_application_id' => $application->id,
            'setup_fee' => $validated['setup_fee'],
            'transaction_percentage' => $validated['transaction_percentage'],
            'transaction_fixed_fee' => $validated['transaction_fixed_fee'],
            'monthly_fee' => $validated['monthly_fee'],
            'monthly_minimum' => $validated['monthly_minimum'],
            'service_fee' => $validated['service_fee'],
            'fees_confirmed' => false,
            'fees_confirmed_at' => null,
        ]);

        // Fire event to send email notification
        event(new FeesChangedEvent($newApplication, $application));

        return Redirect::route('applications.edit', $newApplication)
            ->with('success', 'New application created with updated fees. Email notification sent to account.');
    }

    public function setEmailReminder(Application $application): RedirectResponse
    {
        $validated = Request::validate([
            'interval' => ['required', 'in:1_day,3_days,1_week,2_weeks,1_month'],
        ]);

        // Deactivate existing reminders
        $application->emailReminders()
            ->where('email_type', 'application_created')
            ->update(['is_active' => false]);

        // Create new reminder
        $intervals = [
            '1_day' => now()->addDay(),
            '3_days' => now()->addDays(3),
            '1_week' => now()->addWeek(),
            '2_weeks' => now()->addWeeks(2),
            '1_month' => now()->addMonth(),
        ];

        EmailReminder::create([
            'remindable_type' => Application::class,
            'remindable_id' => $application->id,
            'email_type' => 'application_created',
            'interval' => $validated['interval'],
            'next_send_at' => $intervals[$validated['interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'Email reminder configured.');
    }

    public function cancelEmailReminder(Application $application): RedirectResponse
    {
        $application->emailReminders()
            ->where('email_type', 'application_created')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'Email reminder cancelled.');
    }

    public function destroy(Application $application): RedirectResponse
    {
        $application->delete();

        return Redirect::back()->with('success', 'Application deleted.');
    }

    public function restore(Application $application): RedirectResponse
    {
        $application->restore();

        return Redirect::back()->with('success', 'Application restored.');
    }
}