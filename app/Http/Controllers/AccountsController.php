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
        $accounts = Account::query()
            ->with('applications')
            ->orderBy('name')
            ->filter(Request::only('search', 'trashed'))
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'user_name' => $account->user?->first_name . ' ' . $account->user?->last_name,
                'applications_count' => $account->applications->count(),
                'applications' => $account->applications->map(fn ($app) => [
                    'id' => $app->id,
                    'name' => $app->name,
                ]),
                'created_at' => $account->created_at ? $account->created_at->toDateTimeString() : null,
                'updated_at' => $account->updated_at ? $account->updated_at->toDateTimeString() : null,
            ]);
    
        // Add log before returning
        \Log::info('Accounts index data:', [
            'filters' => Request::all('search', 'trashed'),
            'accounts' => $accounts->items(), // Log only items, not paginator metadata
        ]);
    
        return Inertia::render('Accounts/Index', [
            'filters' => Request::all('search', 'trashed'),
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