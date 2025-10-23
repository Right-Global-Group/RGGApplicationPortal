<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\DocuSignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationStatusController extends Controller
{
    public function __construct(
        private DocuSignService $docuSignService
    ) {}

    public function show(Application $application): Response
    {
        // Check permissions
        $isAccount = auth()->guard('account')->check();
        $canViewStatus = false;
        
        if ($isAccount && $application->account_id === auth()->guard('account')->id()) {
            $canViewStatus = true;
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            if ($user->isAdmin() || $application->account->user_id === $user->id) {
                $canViewStatus = true;
            }
        }
        
        if (!$canViewStatus) {
            abort(403, 'Unauthorized access.');
        }

        return Inertia::render('Applications/Status', [
            'application' => [
                'id' => $application->id,
                'name' => $application->name,
                'trading_name' => $application->trading_name,
                'setup_fee' => $application->setup_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'monthly_fee' => $application->monthly_fee,
                'monthly_minimum' => $application->monthly_minimum,
                'service_fee' => $application->service_fee,
                'fees_confirmed' => $application->fees_confirmed,
                'fees_confirmed_at' => $application->fees_confirmed_at?->format('Y-m-d H:i'),
                'status' => $application->status ? [
                    'current_step' => $application->status->current_step,
                    'progress_percentage' => $application->status->progress_percentage,
                    'requires_additional_info' => $application->status->requires_additional_info,
                    'additional_info_notes' => $application->status->additional_info_notes,
                    'step_history' => $application->status->step_history,
                    'timestamps' => [
                        'fees_confirmed' => $application->status->fees_confirmed_at?->format('Y-m-d H:i'),
                        'contract_sent' => $application->status->contract_sent_at?->format('Y-m-d H:i'),
                        'contract_completed' => $application->status->contract_completed_at?->format('Y-m-d H:i'),
                        'application_approved' => $application->status->application_approved_at?->format('Y-m-d H:i'),
                        'invoice_sent' => $application->status->invoice_sent_at?->format('Y-m-d H:i'),
                        'invoice_paid' => $application->status->invoice_paid_at?->format('Y-m-d H:i'),
                        'account_live' => $application->status->account_live_at?->format('Y-m-d H:i'),
                    ],
                ] : null,
                'documents' => $application->documents()->get()->map(fn ($doc) => [
                    'id' => $doc->id,
                    'type' => $doc->document_type,
                    'status' => $doc->status,
                    'sent_at' => $doc->sent_at?->format('Y-m-d H:i'),
                    'completed_at' => $doc->completed_at?->format('Y-m-d H:i'),
                ]),
                'invoices' => $application->invoices()->get()->map(fn ($inv) => [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'amount' => $inv->amount,
                    'status' => $inv->status,
                    'sent_at' => $inv->sent_at?->format('Y-m-d'),
                    'paid_at' => $inv->paid_at?->format('Y-m-d'),
                    'due_date' => $inv->due_date?->format('Y-m-d'),
                ]),
                'gateway' => $application->gatewayIntegration ? [
                    'provider' => $application->gatewayIntegration->gateway_provider,
                    'status' => $application->gatewayIntegration->status,
                    'merchant_id' => $application->gatewayIntegration->merchant_id,
                ] : null,
                'activity_logs' => $application->activityLogs()
                    ->with('user')
                    ->latest()
                    ->take(20)
                    ->get()
                    ->map(fn ($log) => [
                        'action' => $log->action,
                        'description' => $log->description,
                        'user_name' => $log->user?->name,
                        'created_at' => $log->created_at->format('Y-m-d H:i'),
                    ]),
            ],
            'is_account' => $isAccount,
        ]);
    }

    public function confirmFees(Application $application): RedirectResponse
    {
        // Only accounts can confirm fees
        if (!auth()->guard('account')->check() || $application->account_id !== auth()->guard('account')->id()) {
            abort(403, 'Only the associated account can confirm fees.');
        }

        if ($application->fees_confirmed) {
            return Redirect::back()->with('error', 'Fees have already been confirmed.');
        }

        $application->confirmFees();

        return Redirect::back()->with('success', 'Fees confirmed successfully.');
    }

    public function updateStep(Application $application): RedirectResponse
    {
        $validated = Request::validate([
            'step' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $application->status->transitionTo($validated['step'], $validated['notes'] ?? null);

        return Redirect::back()->with('success', 'Application status updated.');
    }

    /**
     * Send contract link via DocuSign - returns JSON with signing URL
     */
    public function sendContractLink(Application $application): JsonResponse
    {
        try {
            // Send via DocuSign and get signing URL
            $result = $this->docuSignService->sendContract($application);
            
            $application->status->update([
                'docusign_envelope_id' => $result['envelope_id'],
                'docusign_status' => 'sent',
            ]);

            $application->status->transitionTo('application_sent', 'Contract sent via DocuSign');

            return response()->json([
                'success' => true,
                'message' => 'Contract sent successfully',
                'signing_url' => $result['signing_url'],
                'envelope_id' => $result['envelope_id'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send contract: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function markAsApproved(Application $application): RedirectResponse
    {
        $application->status->transitionTo('application_approved', 'Application manually approved');

        return Redirect::back()->with('success', 'Application approved.');
    }

    /**
     * Callback after DocuSign signing is complete
     */
    public function docusignCallback(Application $application): Response
    {
        $event = Request::query('event');
        
        if ($event === 'signing_complete') {
            // Find the most recent sent DocuSign document
            $document = $application->documents()
                ->where('external_system', 'docusign')
                ->where('status', 'sent')
                ->latest()
                ->first();
                
            if ($document) {
                // Update document status
                $document->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                
                // Update application status
                $application->status->transitionTo('contract_completed', 'Contract signed via DocuSign');
                $application->status->transitionTo('contract_submitted', 'Contract automatically submitted');
            }
            
            // Return a view that closes the window and notifies the opener
            return Inertia::render('DocuSign/Callback', [
                'success' => true,
                'message' => 'Contract signed successfully!',
            ]);
        }
        
        return Inertia::render('DocuSign/Callback', [
            'success' => false,
            'message' => 'Contract signing session ended.',
        ]);
    }
}