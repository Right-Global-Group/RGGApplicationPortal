<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Applications/Index', [
            'filters' => Request::all('search', 'trashed'),
            'applications' => Auth::user()->account->applications()
                ->orderBy('name')
                ->filter(Request::only('search', 'trashed'))
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($application) => [
                    'id' => $application->id,
                    'name' => $application->name,
                    'phone' => $application->phone,
                    'city' => $application->city,
                    'deleted_at' => $application->deleted_at,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Applications/Create');
    }

    public function store(): RedirectResponse
    {
        Auth::user()->account->applications()->create(
            Request::validate([
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

        return Redirect::route('applications')->with('success', 'Application created.');
    }

    public function edit(Application $application): Response
    {
        return Inertia::render('Applications/Edit', [
            'application' => [
                'id' => $application->id,
                'name' => $application->name,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
                'city' => $application->city,
                'region' => $application->region,
                'country' => $application->country,
                'postal_code' => $application->postal_code,
                'deleted_at' => $application->deleted_at,
                'contacts' => $application->contacts()->orderByName()->get()->map->only('id', 'name', 'city', 'phone'),
            ],
        ]);
    }

    public function update(Application $application): RedirectResponse
    {
        $application->update(
            Request::validate([
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
