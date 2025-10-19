<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Account;
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

        return Inertia::render('Applications/Create', [
            'accounts' => Account::orderBy('name')->get()->map->only('id', 'name'),
            'preselected_account_id' => $accountId ? (int) $accountId : null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $application = Application::create(array_merge(
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
            ]),
            ['user_id' => auth()->id()]
        ));

        return Redirect::route('applications.status', $application)->with('success', 'Application created.');
    }

    public function edit(Application $application): Response
    {
        $application->load(['account', 'user']);

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
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
                'city' => $application->city,
                'region' => $application->region,
                'country' => $application->country,
                'postal_code' => $application->postal_code,
                'deleted_at' => $application->deleted_at,
                'created_at' => $application->created_at,
                'updated_at' => $application->updated_at,
            ],
            'accounts' => Account::orderBy('name')->get()->map->only('id', 'name'),
        ]);
    }

    public function update(Application $application): RedirectResponse
    {
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