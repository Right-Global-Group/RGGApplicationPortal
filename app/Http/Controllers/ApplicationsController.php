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

    /**
     * Store a newly created application
     * Automatically sends credentials email if account hasn't logged in yet
     */
    public function store(): RedirectResponse
    {
        $validated = Request::validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'name' => ['required', 'max:100'],
            'email' => ['nullable', 'max:50', 'email'],
            'scaling_fee' => ['nullable', 'integer', 'min:0'],
            'phone' => ['nullable', 'max:50'],
            'scaling_fee' => ['required', 'numeric', 'min:0'],
            'transaction_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'transaction_fixed_fee' => ['required', 'numeric', 'min:0'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'monthly_minimum' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
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

        // Send credentials email if account hasn't logged in yet
        $credentialsSent = false;
        if (!$account->first_login_at) {
            // Generate new password
            $plainPassword = Account::generatePassword();
            $account->update(['password' => $plainPassword]);

            // Fire event to send credentials email
            // event(new \App\Events\AccountCredentialsEvent($account, $plainPassword));
            
            $credentialsSent = true;
        }

        $successMessage = 'Application created successfully.';
        if ($credentialsSent) {
            $successMessage .= ' Credentials email sent to account holder.';
        }

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
        $canEditCardstream = auth()->guard('web')->check();
    
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
                'scaling_fee' => $application->scaling_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'scaling_fee' => $application->scaling_fee,
                'monthly_fee' => $application->monthly_fee,
                'monthly_minimum' => $application->monthly_minimum,
                'setup_fee' => $application->setup_fee,
                'deleted_at' => $application->deleted_at,
                'created_at' => $application->created_at,
                'updated_at' => $application->updated_at,
                'wordpress_url' => $application->wordpress_url,
                'wordpress_username' => $application->wordpress_username,
                'wordpress_password' => $application->wordpress_password,
                'wordpress_credentials_entered_at' => $application->wordpress_credentials_entered_at,
                'cardstream_username' => $application->cardstream_username,
                'cardstream_password' => $application->cardstream_password,
                'cardstream_merchant_id' => $application->cardstream_merchant_id,
                'cardstream_credentials_entered_at' => $application->cardstream_credentials_entered_at,
                'can_merchant_sign' => auth()->guard('account')->check() 
                ? $this->canMerchantSignContract($application) 
                : false,  // Don't check if not authenticated
                'status' => $application->status ? [
                    'current_step' => $application->status->current_step,
                    'progress_percentage' => $application->status->progress_percentage,
                    'timestamps' => [
                        'created' => $application->status->created_at?->format('Y-m-d H:i'),
                        'documents_uploaded' => $application->status->documents_uploaded_at?->format('Y-m-d H:i'),
                        'documents_approved' => $application->status->documents_approved_at?->format('Y-m-d H:i'),
                        'contract_sent' => $application->status->contract_sent_at?->format('Y-m-d H:i'),
                        'contract_signed' => $application->status->contract_signed_at?->format('Y-m-d H:i'),
                        'contract_completed' => $application->status->contract_completed_at?->format('Y-m-d H:i'),
                        'contract_submitted' => $application->status->contract_submitted_at?->format('Y-m-d H:i'),
                        'application_approved' => $application->status->application_approved_at?->format('Y-m-d H:i'),
                        'invoice_sent' => $application->status->invoice_sent_at?->format('Y-m-d H:i'),
                        'invoice_paid' => $application->status->invoice_paid_at?->format('Y-m-d H:i'),
                        'gateway_integrated' => $application->status->gateway_integrated_at?->format('Y-m-d H:i'),
                        'account_live' => $application->status->account_live_at?->format('Y-m-d H:i'),
                    ],
                ] : null,
                'additional_documents' => $application->additionalDocuments()
                    ->with('requestedBy')
                    ->get()
                    ->map(fn ($doc) => [
                        'id' => $doc->id,
                        'document_name' => $doc->document_name,
                        'instructions' => $doc->instructions,
                        'is_uploaded' => $doc->is_uploaded,
                        'requested_by' => $doc->requestedBy?->name,
                        'requested_at' => $doc->requested_at->format('Y-m-d H:i'),
                        'uploaded_at' => $doc->uploaded_at?->format('Y-m-d H:i'),
                    ]),
            ],
            'accounts' => Account::orderBy('name')->get()->map->only('id', 'name'),
            'canChangeFees' => $canChangeFees,
            'canEditCardstream' => $canEditCardstream,
            'canEditCardStream' => auth()->guard('web')->check(),
            'documents' => $application->documents()->get()->map(fn ($doc) => [
                'id' => $doc->id,
                'document_category' => $doc->document_category,
                'original_filename' => $doc->original_filename,
                'uploaded_at' => $doc->created_at?->format('Y-m-d H:i'),
            ]),
            'documentCategories' => ApplicationDocument::getCategoriesForApplication($application),
            'categoryDescriptions' => collect(ApplicationDocument::getCategoriesForApplication($application))
                ->mapWithKeys(fn ($label, $key) => [
                    $key => ApplicationDocument::getCategoryDescriptionForApplication($key, $application)
                ])
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
            'scaling_fee' => ['required', 'numeric', 'min:0'],
            'transaction_percentage' => ['required', 'numeric', 'min:0'],
            'transaction_fixed_fee' => ['required', 'numeric', 'min:0'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'scaling_fee' => ['nullable', 'integer', 'min:1'],
            'monthly_minimum' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
        ]);

        // Create new application with updated fees
        $newApplication = Application::create([
            'account_id' => $application->account_id,
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'parent_application_id' => $application->id,
            'scaling_fee' => $validated['scaling_fee'],
            'transaction_percentage' => $validated['transaction_percentage'],
            'transaction_fixed_fee' => $validated['transaction_fixed_fee'],
            'monthly_fee' => $validated['monthly_fee'],
            'monthly_minimum' => $validated['monthly_minimum'],
            'setup_fee' => $validated['setup_fee'],
        ]);

        // Fire event to send email notification
        event(new FeesChangedEvent($newApplication, $application));

        return Redirect::route('applications.edit', $newApplication)
            ->with('success', 'New application created with updated fees. Email notification sent to account.');
    }

    /**
     * Update fees directly on the current application (without creating new one)
     */
    public function updateFees(Application $application): RedirectResponse
    {
        // Check permissions - only admins can change fees
        if (!auth()->guard('web')->check() || !auth()->guard('web')->user()->isAdmin()) {
            abort(403, 'Only administrators can change application fees.');
        }

        $validated = Request::validate([
            'scaling_fee' => ['required', 'numeric', 'min:0'],
            'transaction_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'transaction_fixed_fee' => ['required', 'numeric', 'min:0'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'monthly_minimum' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['required', 'numeric', 'min:0'],
        ]);

        $application->update($validated);

        return Redirect::back()->with('success', 'Application fees updated successfully.');
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

    /**
     * Save WordPress credentials (can be done by user or account)
     */
/**
 * Save WordPress credentials (can be done by user or account)
 */
public function saveWordPressCredentials(Application $application): RedirectResponse
{
    \Log::info('saveWordPressCredentials called', [
        'application_id' => $application->id,
        'guard' => auth()->guard('account')->check() ? 'account' : 'web',
    ]);

    // Both users and accounts can save WordPress credentials
    if (auth()->guard('account')->check()) {
        if ($application->account_id !== auth()->guard('account')->id()) {
            abort(403);
        }
    } elseif (auth()->guard('web')->check()) {
        $user = auth()->guard('web')->user();
        if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
            abort(403);
        }
    }

    $validated = Request::validate([
        'wordpress_url' => ['required', 'url', 'max:255'],
        'wordpress_username' => ['required', 'string', 'max:255'],
        'wordpress_password' => ['required', 'string', 'max:255'],
        'send_reminder' => ['boolean'],
        'reminder_interval' => ['required_if:send_reminder,true', 'in:1_day,3_days,1_week,2_weeks,1_month'],
    ]);

    \Log::info('WordPress credentials validated', [
        'send_reminder' => $validated['send_reminder'] ?? false,
        'is_web_guard' => auth()->guard('web')->check(),
    ]);

    $application->update([
        'wordpress_url' => $validated['wordpress_url'],
        'wordpress_username' => $validated['wordpress_username'],
        'wordpress_password' => $validated['wordpress_password'],
        'wordpress_credentials_entered_at' => now(),
    ]);

    // Cancel any active reminders when credentials are saved
    $application->emailReminders()
        ->where('email_type', 'wordpress_credentials_request')
        ->update(['is_active' => false]);

    // Update status timestamp
    if ($application->status) {
        $application->status->update([
            'wordpress_credentials_collected_at' => now()
        ]);
    }

    // If user/admin is saving and wants to send reminder, send request email
    if (auth()->guard('web')->check()) {
        \Log::info('Firing WordPressCredentialsRequestEvent', [
            'application_id' => $application->id,
            'account_email' => $application->account->email,
        ]);

        // Send initial request email to account
        event(new \App\Events\WordPressCredentialsRequestEvent($application));

        // Set up recurring reminder
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
            'email_type' => 'wordpress_credentials_request',
            'interval' => $validated['reminder_interval'],
            'next_send_at' => $intervals[$validated['reminder_interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'WordPress credentials saved and reminder email sent to account.');
    }

    // If account is saving their own credentials, just save without email
    return Redirect::back()->with('success', 'WordPress credentials saved successfully.');
}

/**
 * Send CardStream credentials to account
 */
public function sendCardStreamCredentials(Application $application): RedirectResponse
{
    \Log::info('sendCardStreamCredentials called', [
        'application_id' => $application->id,
    ]);

    // Only users/admins can send CardStream credentials
    if (auth()->guard('account')->check()) {
        abort(403);
    }

    $validated = Request::validate([
        'cardstream_username' => ['required', 'string', 'max:255'],
        'cardstream_password' => ['required', 'string', 'max:255'],
        'cardstream_merchant_id' => ['required', 'string', 'max:255'],
        'send_reminder' => ['boolean'],
        'reminder_interval' => ['required_if:send_reminder,true', 'in:1_day,3_days,1_week,2_weeks,1_month'],
    ]);

    \Log::info('CardStream credentials validated', [
        'send_reminder' => $validated['send_reminder'] ?? false,
        'account_email' => $application->account->email,
    ]);

    // Save credentials
    $application->update([
        'cardstream_username' => $validated['cardstream_username'],
        'cardstream_password' => $validated['cardstream_password'],
        'cardstream_merchant_id' => $validated['cardstream_merchant_id'],
        'cardstream_credentials_entered_at' => now(),
    ]);

    // Cancel any active reminders
    $application->emailReminders()
        ->where('email_type', 'cardstream_credentials')
        ->update(['is_active' => false]);

    \Log::info('Firing CardStreamCredentialsEvent', [
        'application_id' => $application->id,
        'account_email' => $application->account->email,
    ]);

    // ALWAYS send the initial email with credentials
    event(new \App\Events\CardStreamCredentialsReminderEvent(
        $application,
        $validated['cardstream_username'],
        $validated['cardstream_password'],
        $validated['cardstream_merchant_id']
    ));

    // If send_reminder is checked, also set up recurring reminder
    if ($validated['send_reminder'] ?? false) {
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
            'email_type' => 'cardstream_credentials',
            'interval' => $validated['reminder_interval'],
            'next_send_at' => $intervals[$validated['reminder_interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'CardStream credentials sent and recurring reminder scheduled.');
    }

    return Redirect::back()->with('success', 'CardStream credentials sent to account.');
}

    /**
     * Cancel CardStream credentials reminder
     */
    public function cancelCardStreamReminder(Application $application): RedirectResponse
    {
        $application->emailReminders()
            ->where('email_type', 'cardstream_credentials')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'CardStream reminder cancelled.');
    }

    /**
     * Mark gateway as integrated and transition step
     */
    public function markGatewayIntegrated(Application $application): RedirectResponse
    {
        if (auth()->guard('account')->check()) {
            abort(403);
        }

        // Verify invoice is paid
        if (!$application->status->invoice_paid_at) {
            return Redirect::back()->with('error', 'Invoice must be paid before marking gateway as integrated.');
        }

        $application->status->update([
            'gateway_integrated_at' => now()
        ]);

        $application->status->transitionTo(
            'gateway_integrated',
            'Gateway integration marked complete by ' . auth()->user()->name
        );

        return Redirect::back()->with('success', 'Gateway marked as integrated.');
    }

    /**
     * Request WordPress credentials from account
     */
    public function requestWordPressCredentials(Application $application): RedirectResponse
    {
        if (auth()->guard('account')->check()) {
            abort(403);
        }

        $validated = Request::validate([
            'send_reminder' => ['boolean'],
            'reminder_interval' => ['required_if:send_reminder,true', 'in:1_day,3_days,1_week,2_weeks,1_month'],
        ]);

        // Fire event to send email
        event(new \App\Events\WordPressCredentialsRequestEvent($application));

        // Set up reminder if requested
        if ($validated['send_reminder'] ?? false) {
            // Deactivate existing reminders
            $application->emailReminders()
                ->where('email_type', 'wordpress_credentials_request')
                ->update(['is_active' => false]);

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
                'email_type' => 'wordpress_credentials_request',
                'interval' => $validated['reminder_interval'],
                'next_send_at' => $intervals[$validated['reminder_interval']],
                'is_active' => true,
            ]);
        }

        return Redirect::back()->with('success', 'WordPress credentials request sent.');
    }

    /**
     * Cancel WordPress credentials reminder
     */
    public function cancelWordPressReminder(Application $application): RedirectResponse
    {
        $application->emailReminders()
            ->where('email_type', 'wordpress_credentials_request')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'WordPress credentials reminder cancelled.');
    }

    /**
     * Make account live - final step
     */
    public function makeAccountLive(Application $application): RedirectResponse
    {
        if (auth()->guard('account')->check()) {
            abort(403);
        }

        // Verify gateway is integrated
        if (!$application->status->gateway_integrated_at) {
            return Redirect::back()->with('error', 'Gateway must be integrated before making account live.');
        }

        // // Verify WordPress credentials are entered
        // if (!$application->wordpress_credentials_entered_at) {
        //     return Redirect::back()->with('error', 'WordPress credentials must be entered before making account live.');
        // }

        $application->status->update([
            'account_live_at' => now()
        ]);

        $application->status->transitionTo(
            'account_live',
            'Account made live by ' . auth()->user()->name
        );

        // Fire event to send congratulations email
        event(new \App\Events\AccountLiveEvent($application));

        return Redirect::back()->with('success', 'Account is now live! Congratulations email sent.');
    }

    private function canMerchantSignContract(Application $application): bool
    {
        $status = $application->status;
        if (!$status || !$status->contract_sent_at || $status->contract_signed_at) {
            return false;
        }
        
        // Second check: verify routing order using DocuSign
        $envelopeId = $application->status->docusign_envelope_id;
        if (!$envelopeId) {
            return false;
        }
        
        try {
            $accessToken = $this->getDocuSignAccessToken();
            
            $envelopeResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->get(config('services.docusign.base_url') . "/v2.1/accounts/" . config('services.docusign.account_id') . "/envelopes/{$envelopeId}/recipients");
            
            if ($envelopeResponse->failed()) {
                return false;
            }
            
            $envelopeData = $envelopeResponse->json();
            $currentRoutingOrder = $envelopeData['currentRoutingOrder'] ?? 1;
            
            // Find merchant's routing order
            $merchantEmail = strtolower($application->account->email);
            $merchantRoutingOrder = null;
            
            foreach ($envelopeData['signers'] ?? [] as $signer) {
                if (strtolower($signer['email']) === $merchantEmail) {
                    $merchantRoutingOrder = (int)$signer['routingOrder'];
                    break;
                }
            }
            
            // If merchant not found by exact email (imported envelope), try elimination
            if ($merchantRoutingOrder === null && $application->status->current_step === 'contract_sent') {
                foreach ($envelopeData['signers'] ?? [] as $signer) {
                    $signerEmail = strtolower($signer['email']);
                    
                    // Skip G2Pay/internal signers
                    if (stripos($signerEmail, 'g2pay.co.uk') === false && 
                        stripos($signerEmail, 'management@') === false &&
                        stripos($signer['roleName'] ?? '', 'Director') === false &&
                        stripos($signer['roleName'] ?? '', 'Product Manager') === false) {
                        
                        $merchantRoutingOrder = (int)$signer['routingOrder'];
                        break;
                    }
                }
            }
            
            // Merchant can only sign if it's their turn
            return $merchantRoutingOrder !== null && $merchantRoutingOrder <= $currentRoutingOrder;
            
        } catch (\Exception $e) {
            \Log::error('Failed to check merchant signing eligibility', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getDocuSignAccessToken(): string
    {
        $docuSignService = app(\App\Services\DocuSignService::class);
        return $docuSignService->getAccessToken();
    }
}