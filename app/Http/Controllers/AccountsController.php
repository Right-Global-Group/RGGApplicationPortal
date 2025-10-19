<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountsController extends Controller
{
    public function index(): Response
    {
        $query = Account::query()
            ->with(['applications', 'user'])
            ->orderBy('name');

        // Search filter for account name
        if ($search = Request::input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Search filter for creator name
        if ($creatorSearch = Request::input('creator_search')) {
            $query->whereHas('user', function ($q) use ($creatorSearch) {
                $q->where('first_name', 'like', "%{$creatorSearch}%")
                  ->orWhere('last_name', 'like', "%{$creatorSearch}%");
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

        $accounts = $query
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'user_name' => $account->user 
                    ? ($account->user->first_name . ' ' . $account->user->last_name)
                    : null,
                'applications_count' => $account->applications->count(),
                'applications' => $account->applications->map(fn ($app) => [
                    'id' => $app->id,
                    'name' => $app->name,
                ]),
                'deleted_at' => $account->deleted_at,
                'created_at' => $account->created_at?->format('Y-m-d H:i'),
                'updated_at' => $account->updated_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Accounts/Index', [
            'filters' => Request::only(['search', 'creator_search', 'date_from', 'date_to', 'deleted']),
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show the form to create a new account.
     */
    public function create(): Response
    {
        // Pass the currently authenticated user for display in the read-only field
        return Inertia::render('Accounts/Create', [
            'user' => auth()->user()?->only('id', 'first_name', 'last_name'),
        ]);
    }

    /**
     * Store a new account.
     */
    public function store(): RedirectResponse
    {
        // Validate the name and automatically set user_id to the current user
        $account = Account::create(array_merge(
            Request::validate([
                'name' => ['required', 'max:50'],
            ]),
            ['user_id' => auth()->id()]
        ));

        return Redirect::route('accounts.edit', $account)->with('success', 'Account created.');
    }

    /**
     * Show the form to edit an existing account.
     */
    public function edit(Account $account): Response
    {
        // Load the user relation so we can show "Created By" in the form
        $account->load('user');

        $applications = $account->applications()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'city', 'created_at']);

        return Inertia::render('Accounts/Edit', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'user_id' => $account->user_id, // original creator
                'user_name' => $account->user?->first_name . ' ' . $account->user?->last_name,
                'created_at' => $account->created_at?->toDateTimeString(),
                'updated_at' => $account->updated_at?->toDateTimeString(),
            ],
            'applications' => $applications,
        ]);
    }

    /**
     * Update an existing account.
     */
    public function update(Account $account): RedirectResponse
    {
        // Only allow updating the account name
        $account->update(
            Request::validate([
                'name' => ['required', 'max:50'],
            ])
        );

        return Redirect::back()->with('success', 'Account updated.');
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