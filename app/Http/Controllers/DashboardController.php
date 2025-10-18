<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\ApplicationStatus;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $account = Auth::user()->account;

        // 🔹 Count total applications for this account
        $totalApplications = $account->applications()->count();

        // 🔹 Count applications where status.current_step = 'application_approved'
        $approvedApplications = ApplicationStatus::whereHas('application', function ($query) use ($account) {
            $query->where('account_id', $account->id);
        })->where('current_step', 'application_approved')->count();

        // 🔹 Your existing paginated applications list
        $applications = $account->applications()
            ->orderBy('name')
            ->filter($request->only('search', 'trashed'))
            ->paginate(10)
            ->withQueryString()
            ->through(fn($application) => [
                'id' => $application->id,
                'name' => $application->name,
                'phone' => $application->phone,
                'city' => $application->city,
                'deleted_at' => $application->deleted_at,
            ]);

        // 🔹 Return data to Inertia
        return Inertia::render('Dashboard/Index', [
            'applications' => $applications,
            'stats' => [
                'total' => $totalApplications,
                'approved' => $approvedApplications,
            ],
        ]);
    }
}
