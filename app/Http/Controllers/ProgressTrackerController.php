<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Inertia\Inertia;
use Inertia\Response;

class ProgressTrackerController extends Controller
{
    public function index(): Response
    {
        // 🔹 Load all applications, not tied to user
        $applications = Application::query()
            ->with(['status', 'gatewayIntegration'])
            ->latest()
            ->paginate(20)
            ->through(fn ($app) => [
                'id' => $app->id,
                'name' => $app->name,
                'trading_name' => $app->trading_name,
                'current_step' => $app->status?->current_step ?? 'created',
                'progress_percentage' => $app->status?->progress_percentage ?? 0,
                'gateway_provider' => $app->gatewayIntegration?->gateway_provider,
                'requires_attention' => $app->status?->requires_additional_info ?? false,
                'updated_at' => $app->updated_at->format('Y-m-d H:i'),
            ]);

        // 🔹 Calculate stats based on the paginated collection
        $stats = [
            'total_applications' => $applications->total(), // Use total() not count()
            'pending_contracts' => $applications->getCollection()->where('current_step', 'application_sent')->count(),
            'awaiting_approval' => $applications->getCollection()->where('current_step', 'contract_submitted')->count(),
            'awaiting_payment' => $applications->getCollection()->where('current_step', 'invoice_sent')->count(),
            'in_integration' => $applications->getCollection()->whereIn('current_step', ['invoice_paid', 'gateway_integrated'])->count(),
            'live_accounts' => $applications->getCollection()->where('current_step', 'account_live')->count(),
        ];

        return Inertia::render('ProgressTracker/Index', [
            'applications' => $applications,
            'stats' => $stats,
        ]);
    }
}
