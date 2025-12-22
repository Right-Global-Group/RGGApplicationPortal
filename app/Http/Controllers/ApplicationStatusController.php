<?php

namespace App\Http\Controllers;

use App\Events\AdditionalInfoRequestedEvent;
use App\Events\CardStreamSubmissionEvent;
use App\Models\Application;
use App\Models\ApplicationAdditionalDocument;
use App\Models\ApplicationDocument;
use App\Models\EmailReminder;
use App\Services\DocuSignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
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

        $liveRecipientStatus = [];
        if ($application->status->docusign_envelope_id) {
            try {
                $liveRecipientStatus = $this->docuSignService->getEnvelopeRecipients(
                    $application->status->docusign_envelope_id
                );
                
                // Optionally update the stored status
                if (!empty($liveRecipientStatus)) {
                    $application->status->update([
                        'docusign_recipient_status' => $liveRecipientStatus
                    ]);
                }
                
                \Log::info('Fetched live DocuSign recipient status', [
                    'application_id' => $application->id,
                    'recipient_count' => count($liveRecipientStatus),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to fetch live recipient status', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
                // Fall back to stored status
                $liveRecipientStatus = $application->status->docusign_recipient_status ?? [];
            }
        }

        // Check if this is an imported application
        $merchantImport = \App\Models\MerchantImport::where('account_id', $application->account_id)
        ->where('application_id', $application->id)
        ->first();
    
        return Inertia::render('Applications/Status', [
            'application' => [
                'id' => $application->id,
                'name' => $application->name,
                'trading_name' => $application->trading_name,
                'scaling_fee' => $application->scaling_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'scaling_fee' => $application->scaling_fee,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'monthly_fee' => $application->monthly_fee,
                'monthly_minimum' => $application->monthly_minimum,
                'setup_fee' => $application->setup_fee,
                // Gateway partner fields
                'gateway_partner' => $application->gateway_partner,
                'gateway_partner_name' => $application->gateway_partner_name,
                'gateway_mid' => $application->gateway_mid,
                'gateway_integration_details' => $application->gateway_integration_details,
                'has_gateway_details' => $application->hasGatewayDetails(),
                'requires_additional_document' => $application->requires_additional_document,
                'additional_document_name' => $application->additional_document_name,
                'additional_document_instructions' => $application->additional_document_instructions,
                // WordPress fields
                'wordpress_url' => $application->wordpress_url,
                'wordpress_admin_email' => $application->wordpress_admin_email,
                'wordpress_admin_username' => $application->wordpress_admin_username,
                'has_wordpress_credentials' => $application->hasWordPressCredentials(),
                'can_merchant_sign' => $this->canMerchantSignContract($application),
                'is_imported' => $merchantImport !== null,
                'docusign_envelope_url' => $merchantImport && $application->status->docusign_envelope_id
                ? "https://app.docusign.com/documents/details/{$application->status->docusign_envelope_id}"
                : null,
                'status' => $application->status ? [
                    'current_step' => $application->status->current_step,
                    'progress_percentage' => $application->status->progress_percentage,
                    'requires_additional_info' => $application->status->requires_additional_info,
                    'additional_info_notes' => $application->status->additional_info_notes,
                    'step_history' => $application->status->step_history,
                    'timestamps' => [
                        'created' => $application->created_at?->format('Y-m-d H:i'),
                        'documents_uploaded' => $application->status->documents_uploaded_at?->format('Y-m-d H:i'),
                        'documents_approved' => $application->status->documents_approved_at?->format('Y-m-d H:i'),
                        'contract_sent' => $application->status->contract_sent_at?->format('Y-m-d H:i'),
                        'contract_signed' => $application->status->contract_signed_at?->format('Y-m-d H:i'), 
                        'contract_completed' => $application->status->contract_completed_at?->format('Y-m-d H:i'),
                        'contract_submitted' => $application->status->contract_submitted_at?->format('Y-m-d H:i'),
                        'application_approved' => $application->status->application_approved_at?->format('Y-m-d H:i'),
                        'gateway_contract_sent' => $application->status->gateway_contract_sent_at?->format('Y-m-d H:i'),
                        'gateway_contract_signed' => $application->status->gateway_contract_signed_at?->format('Y-m-d H:i'),
                        'wordpress_credentials_collected' => $application->status->wordpress_credentials_collected_at?->format('Y-m-d H:i'),
                        'invoice_sent' => $application->status->invoice_sent_at?->format('Y-m-d H:i'),
                        'invoice_paid' => $application->status->invoice_paid_at?->format('Y-m-d H:i'),
                        'gateway_integrated' => $application->status->gateway_integrated_at?->format('Y-m-d H:i'),
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
                'contractReminder' => $application->emailReminders()
                    ->where('email_type', 'contract_reminder')
                    ->where('is_active', true)
                    ->first(),
                'additional_documents' => $application->additionalDocuments()
                    ->with('requestedBy')
                    ->get()
                    ->map(fn ($doc) => [
                        'id' => $doc->id,
                        'document_name' => $doc->document_name,
                        'instructions' => $doc->instructions,
                        'is_uploaded' => $doc->is_uploaded,
                        'notes' => $doc->notes,
                        'requested_by' => $doc->requestedBy?->name,
                        'requested_at' => $doc->requested_at->format('Y-m-d H:i'),
                        'uploaded_at' => $doc->uploaded_at?->format('Y-m-d H:i'),
                    ]),
                'email_logs' => $application->morphMany(\App\Models\EmailLog::class, 'emailable')
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
            'docusignRecipientStatus' => $liveRecipientStatus,
            'is_account' => $isAccount,
            'is_admin' => $isAdmin,
            'documentCategories' => ApplicationDocument::getCategoriesForApplication($application),
            'categoryDescriptions' => collect(ApplicationDocument::getCategoriesForApplication($application))
                ->mapWithKeys(fn ($label, $key) => [
                    $key => ApplicationDocument::getCategoryDescriptionForApplication($key, $application)
                ])
                ->toArray(),
            // Get active additional info reminder
            'additionalInfoReminder' => $application->emailReminders()
                ->where('email_type', 'additional_info_requested')
                ->where('is_active', true)
                ->first(),
            'wordpressReminder' => $application->emailReminders()
                ->where('email_type', 'wordpress_credentials_reminder')
                ->where('is_active', true)
                ->first(),
            // Account credentials data
            'accountId' => $application->account_id,
            'accountName' => $application->account_name ?? $application->account->name ?? 'Unknown',
            'accountEmail' => $application->account->email,
            'accountMobile' => $application->account->mobile ?? 'Unknown',
            'accountHasLoggedIn' => $application->account->first_login_at !== null,
            'credentialsReminder' => $application->account->emailReminders()
                ->where('email_type', 'account_credentials')
                ->where('is_active', true)
                ->first(),
        ]);
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

    public function requestAdditionalInfo(Application $application): RedirectResponse
    {
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot request additional information.');
        }
    
        $validated = Request::validate([
            'notes' => ['required', 'string', 'max:1000'],
            'request_additional_document' => ['boolean'],
            'additional_document_name' => ['required_if:request_additional_document,true', 'nullable', 'string', 'max:255'],
            'additional_document_instructions' => ['nullable', 'string', 'max:1000'],
        ]);
    
        // ALWAYS create an additional document record (even if no document requested)
        // This way we track ALL additional info requests
        ApplicationAdditionalDocument::create([
            'application_id' => $application->id,
            'document_name' => $validated['additional_document_name'] ?? 'General Additional Information',
            'instructions' => $validated['additional_document_instructions'] ?? null,
            'notes' => $validated['notes'], // Store the general notes here
            'is_uploaded' => !($validated['request_additional_document'] ?? false), // Mark as uploaded if no document required
            'requested_by' => auth()->id(),
            'requested_at' => now(),
            'uploaded_at' => !($validated['request_additional_document'] ?? false) ? now() : null,
        ]);
    
        // Fire event to send email
        event(new AdditionalInfoRequestedEvent(
            $application,
            $validated['notes'],
            $validated['request_additional_document'] ?? false,
            $validated['additional_document_name'] ?? null,
            $validated['additional_document_instructions'] ?? null
        ));
    
        return Redirect::back()->with('success', 'Additional information request sent to account.');
    }
    
    public function setAdditionalInfoReminder(Application $application): RedirectResponse
    {
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot set email reminders.');
        }
    
        $validated = Request::validate([
            'interval' => ['required', 'in:1_day,3_days,1_week,2_weeks,1_month'],
            'notes' => ['required', 'string', 'max:1000'],
            'request_additional_document' => ['boolean'],
            'additional_document_name' => ['required_if:request_additional_document,true', 'nullable', 'string', 'max:255'],
            'additional_document_instructions' => ['nullable', 'string', 'max:1000'],
        ]);
    
        // ALWAYS create an additional document record
        ApplicationAdditionalDocument::create([
            'application_id' => $application->id,
            'document_name' => $validated['additional_document_name'] ?? 'General Additional Information',
            'instructions' => $validated['additional_document_instructions'] ?? null,
            'notes' => $validated['notes'],
            'is_uploaded' => !($validated['request_additional_document'] ?? false),
            'requested_by' => auth()->id(),
            'requested_at' => now(),
            'uploaded_at' => !($validated['request_additional_document'] ?? false) ? now() : null,
        ]);
    
        // Deactivate existing additional info reminders
        $application->emailReminders()
            ->where('email_type', 'additional_info_requested')
            ->update(['is_active' => false]);
    
        // Create new reminder
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
     * Send gateway partner contract via DocuSign
     */
    public function sendGatewayContract(Application $application): JsonResponse
    {
        try {
            if (!$application->gateway_partner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a gateway partner first.',
                ], 400);
            }

            $result = $this->docuSignService->sendGatewayPartnerContract($application);
            
            $application->status->update([
                'gateway_docusign_envelope_id' => $result['envelope_id'],
            ]);

            $application->status->transitionTo('gateway_contract_sent', 'Gateway contract sent via DocuSign');

            return response()->json([
                'success' => true,
                'message' => "Contract sent to {$application->gateway_partner_name} successfully.",
                'signing_url' => $result['signing_url'],
                'envelope_id' => $result['envelope_id'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send gateway contract: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store gateway integration details from partner
     */
    public function storeGatewayDetails(Application $application): RedirectResponse
    {
        $validated = Request::validate([
            'gateway_mid' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
            'integration_url' => ['nullable', 'url'],
            'additional_notes' => ['nullable', 'string'],
        ]);

        $integrationDetails = [
            'api_key' => $validated['api_key'] ?? null,
            'api_secret' => $validated['api_secret'] ?? null,
            'integration_url' => $validated['integration_url'] ?? null,
            'additional_notes' => $validated['additional_notes'] ?? null,
            'received_at' => now()->toISOString(),
        ];

        $application->update([
            'gateway_mid' => $validated['gateway_mid'],
            'gateway_integration_details' => $integrationDetails,
        ]);

        $application->status->transitionTo('gateway_details_received', 'Gateway details received and stored');

        // Automatically request WordPress credentials
        Mail::to($application->account->email)->send(new \App\Mail\WordPressCredentialsRequest($application));

        return Redirect::back()->with('success', 'Gateway details saved successfully. WordPress credentials request sent to merchant.');
    }

    /**
     * Store WordPress credentials from merchant
     */
    public function storeWordPressCredentials(Application $application): RedirectResponse
    {
        $validated = Request::validate([
            'wordpress_url' => ['required', 'url', 'max:255'],
            'wordpress_admin_email' => ['required', 'email', 'max:255'],
            'wordpress_admin_username' => ['required', 'string', 'max:255'],
        ]);

        $application->update($validated);

        $application->status->transitionTo('wordpress_credentials_collected', 'WordPress credentials provided by merchant');

        return Redirect::back()->with('success', 'WordPress credentials saved successfully.');
    }

    /**
     * Send WordPress credentials reminder
     */
    public function sendWordPressCredentialsReminder(Application $application): RedirectResponse
    {
        Mail::to($application->account->email)->send(new \App\Mail\WordPressCredentialsReminder($application));

        return Redirect::back()->with('success', 'WordPress credentials reminder sent.');
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

    public function markDocumentsApproved(Application $application): RedirectResponse
    {
        // Only admins and application creators can approve documents
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot approve documents.');
        }

        $user = auth()->guard('web')->user();
        if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
            abort(403, 'You can only approve documents for applications you manage.');
        }

        $application->status->transitionTo('documents_approved', 'Documents approved by ' . $user->name);

        return Redirect::back()->with('success', 'Documents marked as approved.');
    }

    /**
     * Callback after merchant DocuSign signing is complete
     */
    public function docusignCallback(Application $application): Response
    {
        $event = Request::query('event');
        
        if ($event === 'signing_complete') {
            // Find the most recent sent DocuSign document
            $document = $application->documents()
                ->where('external_system', 'docusign')
                ->where('document_type', 'contract')
                ->where('status', 'sent')
                ->latest()
                ->first();
                
            if ($document) {
                // Update document status
                $document->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]); 
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

    /**
     * Callback after gateway partner DocuSign signing is complete
     */
    public function gatewayDocusignCallback(Application $application): Response
    {
        $event = Request::query('event');
        
        if ($event === 'signing_complete') {
            // Find the most recent gateway contract
            $document = $application->documents()
                ->where('external_system', 'docusign')
                ->where('document_type', 'gateway_contract')
                ->where('status', 'sent')
                ->latest()
                ->first();
                
            if ($document) {
                // Update document status
                $document->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
            
            return Inertia::render('DocuSign/Callback', [
                'success' => true,
                'message' => 'Gateway contract signed successfully!',
            ]);
        }
        
        return Inertia::render('DocuSign/Callback', [
            'success' => false,
            'message' => 'Gateway contract signing session ended.',
        ]);
    }

    /**
     * Send contract link via DocuSign - returns JSON with signing URL
     */
    public function sendContractLink(Application $application): JsonResponse
    {
        try {
            // Send contract via DocuSign
            $result = $this->docuSignService->sendDocuSignContract($application);
            
            $application->status->update([
                'docusign_envelope_id' => $result['envelope_id'],
                'docusign_status' => 'sent',
            ]);
    
            return response()->json([
                'success' => true,
                'message' => 'Contract sent successfully',
                'signing_url' => $result['signing_url'],
                'envelope_id' => $result['envelope_id'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send DocuSign contract', [
                'application_id' => $application->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send contract: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function sendContractReminder(Application $application): RedirectResponse
    {
        // Check permissions
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot send contract reminders.');
        }
    
        $user = auth()->guard('web')->user();
        if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
            abort(403);
        }
    
        // Get the signing URL from DocuSign status
        $signingUrl = $application->status->docusign_signing_url ?? url("/applications/{$application->id}/status");
    
        try {
            $emailData = [
                'account_name' => $application->account->name,
                'application_name' => $application->name,
                'signing_url' => $signingUrl,
                'application_url' => url("/applications/{$application->id}/status"),
            ];
    
            Mail::to($application->account->email)->send(
                new \App\Mail\DynamicEmail('contract_reminder', $emailData)
            );
    
            // Log the email
            \App\Models\EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'contract_reminder',
                'recipient_email' => $application->account->email,
                'subject' => 'Reminder: Contract Awaiting Signature',
                'sent_at' => now(),
            ]);
    
            return Redirect::back()->with('success', 'Contract reminder sent successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to send contract reminder', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
            
            return Redirect::back()->with('error', 'Failed to send contract reminder.');
        }
    }


    /**
     * Send invoice reminder email
     */
    public function sendInvoiceReminder(Application $application): RedirectResponse
    {
        // Check permissions
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot send invoice reminders.');
        }

        $user = auth()->guard('web')->user();
        if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
            abort(403);
        }

        try {
            $emailData = [
                'account_name' => $application->account->name,
                'application_name' => $application->name,
                'application_url' => url("/applications/{$application->id}/status"),
            ];

            Mail::to($application->account->email)->send(
                new \App\Mail\DynamicEmail('invoice_reminder', $emailData)
            );

            // Transition to invoice_sent if not already there
            if (!$application->status->invoice_sent_at) {
                $application->status->transitionTo(
                    'invoice_sent',
                    'Invoice reminder sent by ' . $user->name
                );
            }

            // Log the email
            \App\Models\EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'invoice_reminder',
                'recipient_email' => $application->account->email,
                'subject' => 'Reminder: Invoice Payment Required',
                'sent_at' => now(),
            ]);

            return Redirect::back()->with('success', 'Invoice reminder sent successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to send invoice reminder', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
            
            return Redirect::back()->with('error', 'Failed to send invoice reminder.');
        }
    }

    /**
     * Mark invoice as paid and transition to next step
     */
    public function markInvoiceAsPaid(Application $application): RedirectResponse
    {
        if (auth()->guard('account')->check()) {
            abort(403);
        }

        // Update status
        $application->status->update([
            'invoice_paid_at' => now()
        ]);

        $application->status->transitionTo(
            'invoice_paid',
            'Invoice marked as paid by ' . auth()->user()->name
        );

        return Redirect::back()->with('success', 'Invoice marked as paid.');
    }


    /**
     * Set recurring contract reminder
     */
    public function setContractReminder(Application $application): RedirectResponse
    {
        // Check permissions
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot set email reminders.');
        }

        $user = auth()->guard('web')->user();
        if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
            abort(403);
        }

        $validated = Request::validate([
            'interval' => ['required', 'in:1_day,3_days,1_week,2_weeks'],
        ]);

        // Deactivate existing contract reminders
        $application->emailReminders()
            ->where('email_type', 'contract_reminder')
            ->update(['is_active' => false]);

        // Create new reminder
        $intervals = [
            '1_day' => now()->addDay(),
            '3_days' => now()->addDays(3),
            '1_week' => now()->addWeek(),
            '2_weeks' => now()->addWeeks(2),
        ];

        EmailReminder::create([
            'remindable_type' => Application::class,
            'remindable_id' => $application->id,
            'email_type' => 'contract_reminder',
            'interval' => $validated['interval'],
            'next_send_at' => $intervals[$validated['interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'Contract reminder scheduled successfully.');
    }

    /**
     * Cancel contract reminder
     */
    public function cancelContractReminder(Application $application): RedirectResponse
    {
        // Check permissions
        if (auth()->guard('account')->check()) {
            abort(403);
        }

        $application->emailReminders()
            ->where('email_type', 'contract_reminder')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'Contract reminder cancelled.');
    }

    /**
     * Submit application to CardStream with payout option
     */
    public function submitToCardStream(Application $application): RedirectResponse
    {
        // Check permissions - only admins/users can submit
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot submit applications to CardStream.');
        }

        // Validate payout option
        $validated = Request::validate([
            'payout_option' => ['required', 'in:daily,every_3_days'],
        ]);

        // Update application with payout option
        $application->update([
            'payout_option' => $validated['payout_option'],
        ]);

        // Get the DocuSign envelope ID
        $envelopeId = $application->status->docusign_envelope_id;
        
        if (!$envelopeId) {
            return Redirect::back()->with('error', 'No signed contract found for this application.');
        }

        // Collect all uploaded documents with their files
        $documents = [];
        
        // Download and attach the DocuSign document (the contract)
        try {
            $docusignPdf = $this->docuSignService->downloadEnvelopeDocument($envelopeId, '1');
            $tempPath = storage_path('app/temp/docusign_contract_' . $application->id . '.pdf');
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            // Save the base64 decoded PDF to temp file
            file_put_contents($tempPath, base64_decode($docusignPdf));
            
            $documents[] = [
                'category' => 'Signed Contract',
                'filename' => "Signed_Contract_{$application->name}.pdf",
                'path' => $tempPath,
                'mime' => 'application/pdf',
                'is_temp' => true,
            ];
            
            \Log::info('DocuSign contract downloaded for CardStream submission', [
                'application_id' => $application->id,
                'envelope_id' => $envelopeId,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to download DocuSign contract', [
                'envelope_id' => $envelopeId,
                'error' => $e->getMessage(),
            ]);
            
            return Redirect::back()->with('error', 'Failed to download signed contract from DocuSign. Please try again.');
        }
        
        // Get standard documents
        foreach ($application->documents as $doc) {
            try {
                if (empty($doc->file_path)) {
                    \Log::warning('Document has no file path', [
                        'document_id' => $doc->id,
                        'document_category' => $doc->document_category,
                    ]);
                    continue;
                }
                
                if (\Storage::disk('public')->exists($doc->file_path)) {
                    $documents[] = [
                        'category' => $this->formatDocumentCategory($doc->document_category),
                        'filename' => $doc->original_filename,
                        'path' => storage_path('app/public/' . $doc->file_path),
                        'mime' => \Storage::disk('public')->mimeType($doc->file_path),
                        'is_temp' => false,
                    ];
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to access document file', [
                    'document_id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $documentUrl = "https://app.docusign.com/documents/details/{$envelopeId}";

        // Update application status
        $application->status->transitionTo(
            'contract_submitted',
            'Application submitted to CardStream with ' . str_replace('_', ' ', $validated['payout_option']) . ' payout by ' . auth()->user()->name
        );

        // Fire event to send email with documents
        event(new CardStreamSubmissionEvent(
            $application, 
            $documentUrl, 
            $documents, 
            $validated['payout_option'] // Pass the payout option
        ));
        // Clean up temporary DocuSign file
        foreach ($documents as $doc) {
            if (isset($doc['is_temp']) && $doc['is_temp'] && file_exists($doc['path'])) {
                @unlink($doc['path']);
            }
        }

        return Redirect::back()->with('success', 'Application submitted to CardStream successfully with ' . str_replace('_', ' ', $validated['payout_option']) . ' payout option.');
    }

    private function formatDocumentCategory(string $category): string
    {
        if (str_starts_with($category, 'additional_requested_')) {
            return 'Additional Document';
        }
        
        return ucwords(str_replace('_', ' ', $category));
    }

    /**
     * Send message from account to user (immediate)
     */
    public function sendAccountMessage(Application $application): RedirectResponse
    {
        // Ensure only accounts can send
        if (!auth()->guard('account')->check()) {
            abort(403, 'Only accounts can send messages.');
        }

        // Ensure account owns this application
        if ($application->account_id !== auth()->guard('account')->id()) {
            abort(403, 'You can only send messages for your own applications.');
        }

        $validated = Request::validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // Store in application_statuses
        $application->status->update([
            'account_message_notes' => $validated['message']
        ]);

        // Fire event to send email immediately
        event(new \App\Events\AccountMessageToUserEvent(
            $application,
            $validated['message']
        ));

        return Redirect::back()->with('success', 'Message sent to administrator.');
    }

    /**
     * Set account message reminder (schedules future emails)
     */
    public function setAccountMessageReminder(Application $application): RedirectResponse
    {
        // Ensure only accounts can set reminders
        if (!auth()->guard('account')->check()) {
            abort(403, 'Only accounts can set message reminders.');
        }

        // Ensure account owns this application
        if ($application->account_id !== auth()->guard('account')->id()) {
            abort(403, 'You can only set reminders for your own applications.');
        }

        $validated = Request::validate([
            'message' => ['required', 'string', 'max:2000'],
            'interval' => ['required', 'in:1_day,3_days,1_week,2_weeks,1_month'],
        ]);

        // Store in application_statuses
        $application->status->update([
            'account_message_notes' => $validated['message']
        ]);

        // Deactivate existing account message reminders
        $application->emailReminders()
            ->where('email_type', 'account_message_to_user')
            ->update(['is_active' => false]);

        // Create new reminder
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
            'email_type' => 'account_message_to_user',
            'interval' => $validated['interval'],
            'next_send_at' => $intervals[$validated['interval']],
            'is_active' => true,
        ]);

        return Redirect::back()->with('success', 'Message reminder scheduled to send ' . str_replace('_', ' ', $validated['interval']) . '.');
    }

    /**
     * Cancel account message reminder
     */
    public function cancelAccountMessageReminder(Application $application): RedirectResponse
    {
        // Ensure only accounts can cancel
        if (!auth()->guard('account')->check()) {
            abort(403, 'Only accounts can cancel message reminders.');
        }

        // Ensure account owns this application
        if ($application->account_id !== auth()->guard('account')->id()) {
            abort(403, 'You can only cancel reminders for your own applications.');
        }

        $application->emailReminders()
            ->where('email_type', 'account_message_to_user')
            ->update(['is_active' => false]);

        return Redirect::back()->with('success', 'Message reminder cancelled.');
    }

    private function canMerchantSignContract(\App\Models\Application $application): bool
    {
        $status = $application->status;
        
        // First check: contract must be sent but not signed
        if (!$status || !$status->contract_sent_at || $status->contract_signed_at) {
            \Log::info('Merchant cannot sign yet');
            return false;
        }
                
        // Second check: verify routing order using DocuSign
        $envelopeId = $status->docusign_envelope_id;
        
        if (!$envelopeId) {
            \Log::info('No envelope ID found');
            return false;
        }
                
        try {
            $accessToken = $this->docuSignService->getAccessToken();
            
            $envelopeResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->get(config('services.docusign.base_url') . "/v2.1/accounts/" . config('services.docusign.account_id') . "/envelopes/{$envelopeId}/recipients");
            
            if ($envelopeResponse->failed()) {
                \Log::error('DocuSign API call failed', [
                    'status' => $envelopeResponse->status(),
                ]);
                return false;
            }
            
            $envelopeData = $envelopeResponse->json();
            $currentRoutingOrder = $envelopeData['currentRoutingOrder'] ?? 1;
            
            // Find merchant's routing order
            $merchantEmail = strtolower($application->account->email);
            $merchantRoutingOrder = null;
                        
            foreach ($envelopeData['signers'] ?? [] as $signer) {
                if (strtolower($signer['email']) === $merchantEmail) {
                    $merchantRoutingOrder = (int)$signer['routingOrder'];

                    break;
                }
            }
            
            // If merchant not found by exact email (imported envelope), try elimination
            if ($merchantRoutingOrder === null && $status->current_step === 'contract_sent') {
                
                foreach ($envelopeData['signers'] ?? [] as $signer) {
                    $signerEmail = strtolower($signer['email']);
                    
                    // Skip G2Pay/internal signers
                    if (stripos($signerEmail, 'g2pay.co.uk') === false && 
                        stripos($signerEmail, 'management@') === false &&
                        stripos($signer['roleName'] ?? '', 'Director') === false &&
                        stripos($signer['roleName'] ?? '', 'Product Manager') === false) {
                        
                        $merchantRoutingOrder = (int)$signer['routingOrder'];

                        break;
                    }
                }
            }
            
            if ($merchantRoutingOrder === null) {
                \Log::error('❌ Merchant not found in envelope');
                return false;
            }
            
            $canSign = $merchantRoutingOrder <= $currentRoutingOrder;
            
            return $canSign;
            
        } catch (\Exception $e) {
            \Log::error('💥 Exception in canMerchantSignContract', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}