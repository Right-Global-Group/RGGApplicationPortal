<?php

namespace App\Http\Controllers;

use App\Events\AccountCredentialsEvent;
use App\Models\Account;
use App\Models\EmailReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountsController extends Controller
{
    public function index(): Response
    {
        $query = Account::query()->with(['applications', 'user']);

        // Access control based on authentication guard
        if (auth()->guard('account')->check()) {
            // Accounts can only see themselves
            $query->where('id', auth()->guard('account')->id());
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            if ($user->isAdmin()) {
                // Admins see all accounts
                // No filtering needed
            } else {
                // Regular users can't access accounts list at all
                abort(403, 'Only administrators can access the accounts list.');
            }
        } else {
            abort(403, 'Unauthorized access.');
        }

        $query->orderBy('name');

        // Filters (same as before)
        if ($search = Request::input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($creatorSearch = Request::input('creator_search')) {
            $query->whereHas('user', function ($q) use ($creatorSearch) {
                $q->where('first_name', 'like', "%{$creatorSearch}%")
                  ->orWhere('last_name', 'like', "%{$creatorSearch}%");
            });
        }

        if ($dateFrom = Request::input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = Request::input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($deleted = Request::input('deleted')) {
            if ($deleted === 'with') {
                $query->withTrashed();
            } elseif ($deleted === 'only') {
                $query->onlyTrashed();
            }
        }

        $accounts = $query
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'status' => $account->status,
                'is_confirmed' => $account->isConfirmed(),
                'user_name' => $account->user 
                    ? ($account->user->first_name . ' ' . $account->user->last_name)
                    : null,
                'applications_count' => $account->applications->count(),
                'applications' => $account->applications->map(fn ($app) => [
                    'id' => $app->id,
                    'name' => $app->name,
                ]),
                'credentials_sent_at' => $account->credentials_sent_at?->format('Y-m-d H:i'),
                'first_login_at' => $account->first_login_at?->format('Y-m-d H:i'),
                'deleted_at' => $account->deleted_at,
                'created_at' => $account->created_at?->format('Y-m-d H:i'),
                'updated_at' => $account->updated_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Accounts/Index', [
            'filters' => Request::only(['search', 'creator_search', 'date_from', 'date_to', 'deleted']),
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create', [
            'user' => auth()->user()?->only('id', 'first_name', 'last_name'),
        ]);
    }

    public function store(): RedirectResponse
    {
        $validated = Request::validate([
            'name' => ['required', 'max:50'],
            'email' => ['required', 'email', 'unique:accounts,email'],
        ]);

        // Generate random password
        $plainPassword = Account::generatePassword();

        $account = Account::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $plainPassword,
            'user_id' => auth()->id(),
            'status' => Account::STATUS_PENDING,
        ]);

        return Redirect::route('accounts.edit', $account)->with('success', 'Account created.');
    }

    public function edit(Account $account): Response
    {
        // Access control
        if (auth()->guard('account')->check()) {
            // Accounts can only view/edit themselves
            if ($account->id !== auth()->guard('account')->id()) {
                abort(403, 'You can only view your own account.');
            }
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            if (!$user->isAdmin() && $account->user_id !== $user->id) {
                abort(403, 'You can only view accounts you created.');
            }
        } else {
            abort(403, 'Unauthorized access.');
        }

        $account->load(['user', 'emailReminders', 'emailLogs']);

        $applications = $account->applications()
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        return Inertia::render('Accounts/Edit', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'status' => $account->status,
                'is_confirmed' => $account->isConfirmed(),
                'user_id' => $account->user_id,
                'user_name' => $account->user?->first_name . ' ' . $account->user?->last_name,
                'credentials_sent_at' => $account->credentials_sent_at?->format('Y-m-d H:i'),
                'first_login_at' => $account->first_login_at?->format('Y-m-d H:i'),
                'created_at' => $account->created_at?->toDateTimeString(),
                'updated_at' => $account->updated_at?->toDateTimeString(),
            ],
            'applications' => $applications,
            'emailReminder' => $account->emailReminders()
                ->where('email_type', 'account_credentials')
                ->where('is_active', true)
                ->first(),
            'emailLogs' => $account->emailLogs()
                ->orderBy('sent_at', 'desc')
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'email_type' => $log->email_type,
                    'subject' => $log->subject,
                    'sent_at' => $log->sent_at->format('Y-m-d H:i'),
                    'opened' => $log->opened,
                ]),
        ]);
    }

    public function update(Account $account): RedirectResponse
    {
        $account->update(
            Request::validate([
                'name' => ['required', 'max:50'],
                'email' => ['required', 'email', 'unique:accounts,email,' . $account->id],
            ])
        );

        return Redirect::back()->with('success', 'Account updated.');
    }

    /**
     * Send credentials email NOW (immediate, not scheduled)
     */
    public function sendCredentialsEmail(Account $account): RedirectResponse
    {
        // Generate new password
        $plainPassword = Account::generatePassword();
        $account->update(['password' => $plainPassword]);

        // Fire event to send email immediately
        event(new AccountCredentialsEvent($account, $plainPassword));

        return Redirect::back()->with('success', 'Credentials email sent immediately to account.');
    }

    /**
     * Set credentials email reminder (schedules future emails, does NOT send now)
     */
    public function setCredentialsReminder(Account $account): RedirectResponse
    {
        $validated = Request::validate([
            'interval' => ['required', 'in:1_day,3_days,1_week,2_weeks,1_month'],
        ]);

        // Deactivate existing reminders
        $account->emailReminders()
            ->where('email_type', 'account_credentials')
            ->update(['is_active' => false]);

        // Create new reminder (scheduled for future, not sent now)
        $intervals = [
            '1_day' => now()->addDay(),
            '3_days' => now()->addDays(3),
            '1_week' => now()->addWeek(),
            '2_weeks' => now()->addWeeks(2),
            '1_month' => now()->addMonth(),
        ];

        EmailReminder::create([
            'remindable_type' => Account::class,
            'remindable_id' => $account->id,
            'email_type' => 'account_credentials',
            'interval' => $validated['interval'],
            'next_send_at' => $intervals[$validated['interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'Reminder scheduled to send credentials ' . str_replace('_', ' ', $validated['interval']) . '.');
    }

    /**
     * Cancel credentials reminder
     */
    public function cancelCredentialsReminder(Account $account): RedirectResponse
    {
        $account->emailReminders()
            ->where('email_type', 'account_credentials')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'Credentials reminder cancelled.');
    }

    // Legacy method kept for backward compatibility (can be removed if no longer used)
    public function setEmailReminder(Account $account): RedirectResponse
    {
        return $this->setCredentialsReminder($account);
    }

    // Legacy method kept for backward compatibility (can be removed if no longer used)
    public function cancelEmailReminder(Account $account): RedirectResponse
    {
        return $this->cancelCredentialsReminder($account);
    }

    public function destroy(Account $account): RedirectResponse
    {
        $account->delete();

        return Redirect::back()->with('success', 'Account deleted.');
    }

    public function restore(Account $account): RedirectResponse
    {
        $account->restore();

        return Redirect::back()->with('success', 'Account restored.');
    }
}