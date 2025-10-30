<?php

namespace App\Http\Controllers;

use App\Events\AdditionalInfoRequestedEvent;
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
                'status' => $application->status ? [
                    'current_step' => $application->status->current_step,
                    'progress_percentage' => $application->status->progress_percentage,
                    'requires_additional_info' => $application->status->requires_additional_info,
                    'additional_info_notes' => $application->status->additional_info_notes,
                    'step_history' => $application->status->step_history,
                    'timestamps' => [
                        'documents_uploaded' => $application->status->documents_uploaded_at?->format('Y-m-d H:i'),
                        'documents_approved' => $application->status->documents_approved_at?->format('Y-m-d H:i'),
                        'contract_sent' => $application->status->contract_sent_at?->format('Y-m-d H:i'),
                        'contract_completed' => $application->status->contract_completed_at?->format('Y-m-d H:i'),
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
            // NEW: Account credentials data
            'accountId' => $application->account_id,
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
     * Send merchant contract link via DocuSign - returns JSON with signing URL
     */
    public function sendContractLink(Application $application): JsonResponse
    {
        try {
            // Send merchant contract via DocuSign
            $result = $this->docuSignService->sendMerchantContract($application);
            
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
                
                // Update application status
                $application->status->transitionTo('gateway_contract_signed', 'Gateway contract signed via DocuSign');
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
}