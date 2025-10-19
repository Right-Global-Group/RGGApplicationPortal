<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\ApplicationStatus;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // 🔹 Count total applications (from all accounts)
        $totalApplications = Application::count();

        // 🔹 Count approved applications (where status.current_step = 'application_approved')
        $approvedApplications = ApplicationStatus::where('current_step', 'application_approved')->count();

        // 🔹 Paginated applications list (global, not user-specific)
        $applications = Application::query()
            ->with('account')
            ->orderBy('name')
            ->filter($request->only('search', 'trashed'))
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($application) => [
                'id' => $application->id,
                'name' => $application->name,
                'account_id' => $application->account_id,
                'account_name' => $application->account?->name,
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
