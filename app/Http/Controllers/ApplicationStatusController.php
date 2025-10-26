<?php

namespace App\Http\Controllers;

use App\Events\AdditionalInfoRequestedEvent;
use App\Models\Application;
use App\Models\EmailReminder;
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
        $isAdmin = false;
        $canViewStatus = false;
        
        if ($isAccount && $application->account_id === auth()->guard('account')->id()) {
            $canViewStatus = true;
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            $isAdmin = $user?->isAdmin() ?? false;
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
                        'documents_uploaded' => $application->status->documents_uploaded_at?->format('Y-m-d H:i'),
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
                    'document_category' => $doc->document_category,
                    'original_filename' => $doc->original_filename,
                    'status' => $doc->status,
                    'uploaded_at' => $doc->created_at?->format('Y-m-d H:i'),
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
                'email_logs' => $application->morphMany(\App\Models\EmailLog::class, 'emailable')
                    ->latest()
                    ->get()
                    ->map(fn ($log) => [
                        'id' => $log->id,
                        'email_type' => $log->email_type,
                        'recipient_email' => $log->recipient_email,
                        'subject' => $log->subject,
                        'sent_at' => $log->sent_at?->format('Y-m-d H:i'),
                        'opened' => $log->opened,
                        'opened_at' => $log->opened_at?->format('Y-m-d H:i'),
                    ]),
                'scheduled_emails' => $application->morphMany(\App\Models\EmailReminder::class, 'remindable')
                    ->where('is_active', true)
                    ->get()
                    ->map(fn ($reminder) => [
                        'id' => $reminder->id,
                        'email_type' => $reminder->email_type,
                        'interval' => $reminder->interval,
                        'next_send_at' => $reminder->next_send_at?->format('Y-m-d H:i'),
                        'is_active' => $reminder->is_active,
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
            'is_admin' => $isAdmin,
            'documentCategories' => \App\Models\ApplicationDocument::getRequiredCategories(),
            'categoryDescriptions' => collect(\App\Models\ApplicationDocument::getRequiredCategories())
                ->mapWithKeys(fn ($label, $key) => [$key => \App\Models\ApplicationDocument::getCategoryDescription($key)])
                ->toArray(),
            // Get active additional info reminder
            'additionalInfoReminder' => $application->emailReminders()
                ->where('email_type', 'additional_info_requested')
                ->where('is_active', true)
                ->first(),
            'feesReminder' => $application->emailReminders()
                ->where('email_type', 'fees_confirmation_reminder')
                ->where('is_active', true)
                ->first(),
        ]);
    }

    /**
     * Send fees confirmation reminder NOW (immediate, not scheduled)
     */
    public function sendFeesConfirmationReminder(Application $application): RedirectResponse
    {
        // Only admins and application creators can send reminders
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot send fee confirmation reminders.');
        }

        if ($application->fees_confirmed) {
            return Redirect::back()->with('error', 'Fees have already been confirmed.');
        }

        // Fire event to send email immediately
        event(new \App\Events\FeesConfirmationReminderEvent($application));

        return Redirect::back()->with('success', 'Fees confirmation reminder sent to account.');
    }

    /**
     * Set scheduled fees confirmation reminder (schedules future emails, does NOT send now)
     */
    public function setFeesConfirmationReminder(Application $application): RedirectResponse
    {
        // Only admins and application creators can set reminders
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot set email reminders.');
        }

        if ($application->fees_confirmed) {
            return Redirect::back()->with('error', 'Fees have already been confirmed.');
        }

        $validated = Request::validate([
            'interval' => ['required', 'in:1_day,3_days,1_week,2_weeks,1_month'],
        ]);

        // Deactivate existing fees confirmation reminders
        $application->emailReminders()
            ->where('email_type', 'fees_confirmation_reminder')
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
            'remindable_type' => Application::class,
            'remindable_id' => $application->id,
            'email_type' => 'fees_confirmation_reminder',
            'interval' => $validated['interval'],
            'next_send_at' => $intervals[$validated['interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'Reminder scheduled to send ' . str_replace('_', ' ', $validated['interval']) . '.');
    }

    /**
     * Cancel fees confirmation reminder
     */
    public function cancelFeesConfirmationReminder(Application $application): RedirectResponse
    {
        $application->emailReminders()
            ->where('email_type', 'fees_confirmation_reminder')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'Fees confirmation reminder cancelled.');
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
     * Request additional information from account
     */
    public function requestAdditionalInfo(Application $application): RedirectResponse
    {
        // Only admins and application creators can request additional info
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot request additional information.');
        }

        $validated = Request::validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        // Update application status
        $application->status->update([
            'requires_additional_info' => true,
            'additional_info_notes' => $validated['notes'],
        ]);

        // Fire event to send email
        event(new AdditionalInfoRequestedEvent($application, $validated['notes']));

        return Redirect::back()->with('success', 'Additional information request sent to account.');
    }

    /**
     * Set email reminder for additional info request (schedules future emails, does NOT send now)
     */
    public function setAdditionalInfoReminder(Application $application): RedirectResponse
    {
        // Only admins and application creators can set reminders
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot set email reminders.');
        }

        $validated = Request::validate([
            'interval' => ['required', 'in:1_day,3_days,1_week,2_weeks,1_month'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        // Update application status with notes
        $application->status->update([
            'requires_additional_info' => true,
            'additional_info_notes' => $validated['notes'],
        ]);

        // Deactivate existing additional info reminders
        $application->emailReminders()
            ->where('email_type', 'additional_info_requested')
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
            'remindable_type' => Application::class,
            'remindable_id' => $application->id,
            'email_type' => 'additional_info_requested',
            'interval' => $validated['interval'],
            'next_send_at' => $intervals[$validated['interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'Reminder scheduled to send ' . str_replace('_', ' ', $validated['interval']) . '.');
    }

    /**
     * Cancel additional info reminder
     */
    public function cancelAdditionalInfoReminder(Application $application): RedirectResponse
    {
        $application->emailReminders()
            ->where('email_type', 'additional_info_requested')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'Additional info reminder cancelled.');
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
        $application->update(['status' => 'application_approved', 'approved_at' => now()]);

        // Change Status:
        $application->status->transitionTo('application_approved', 'User marked application as approved');
    
        // Send approval email to account
        event(new \App\Events\ApplicationApprovedEvent($application->account, $application));
    
        return Redirect::back()->with('success', 'Application approved and email sent.');
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