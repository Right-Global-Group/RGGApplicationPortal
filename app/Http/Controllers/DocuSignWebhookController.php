<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\DocuSignService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class DocuSignWebhookController extends Controller
{
    public function __construct(
        private DocuSignService $docuSignService
    ) {}

    /**
     * Handle DocuSign Connect webhook events
     */
    public function handleMerchantWebhook(Request $request): Response
    {
        try {
            $payload = $request->all();
            
            Log::info('DocuSign merchant webhook received', [
                'payload' => $payload
            ]);
    
            // Extract envelope ID from the correct location
            $envelopeId = $payload['data']['envelopeId'] ?? null;
            $event = $payload['event'] ?? null;
            
            Log::info('Extracted webhook data', [
                'envelope_id' => $envelopeId,
                'event' => $event,
            ]);
    
            if (!$envelopeId) {
                Log::warning('DocuSign webhook missing envelope ID');
                return response()->noContent();
            }
    
            // Find the document by envelope ID
            $document = ApplicationDocument::where('external_id', $envelopeId)
                ->where('external_system', 'docusign')
                ->where('document_type', 'contract')
                ->first();
    
            if (!$document) {
                Log::warning('DocuSign webhook: document not found', [
                    'envelope_id' => $envelopeId
                ]);
                return response()->noContent();
            }
    
            $application = $document->application;
    
            Log::info('Found application for envelope', [
                'application_id' => $application->id,
                'application_name' => $application->name,
            ]);
    
            // Handle different event types
            switch ($event) {
                case 'envelope-sent':
                    $document->update(['status' => 'sent']);
                    
                    event(new \App\Events\DocuSignStatusChangeEvent(
                        $application,
                        'sent',
                        'Contract sent to recipient'
                    ));
                    
                    Log::info('Processed envelope-sent event');
                    break;
            
                case 'envelope-delivered':
                case 'recipient-delivered':  // ← ADD THIS LINE
                    $application->status->update(['contract_viewed_at' => now()]);
                    
                    event(new \App\Events\DocuSignStatusChangeEvent(
                        $application,
                        'delivered',
                        'Contract has been delivered and is ready for signing'
                    ));


                    // Send document upload ready email if documents haven't been uploaded yet
                    if (!$application->status->documents_uploaded_at) {
                        event(new \App\Events\DocumentUploadReadyEvent($application));
                        Log::info('Sent document upload ready email to merchant', [
                            'application_id' => $application->id,
                            'merchant_email' => $application->account->email,
                        ]);
                    }
                    
                    Log::info('Processed envelope-delivered event');
                    break;
            
                case 'recipient-viewed':
                    $application->status->update(['contract_viewed_at' => now()]);
                    
                    event(new \App\Events\DocuSignStatusChangeEvent(
                        $application,
                        'viewed',
                        'Contract has been opened and viewed'
                    ));
                    
                    Log::info('Processed recipient-viewed event');
                    break;
        
                case 'recipient-completed':
                    Log::info('Starting recipient-completed processing');
                    
                    try {
                        // Query DocuSign for all recipient statuses
                        Log::info('About to call getEnvelopeRecipients', ['envelope_id' => $envelopeId]);
                        $recipients = $this->docuSignService->getEnvelopeRecipients($envelopeId);
                        Log::info('Successfully retrieved recipients', [
                            'count' => count($recipients),
                            'recipients' => $recipients
                        ]);
                        
                        // Merge with existing recipients instead of replacing
                        $currentRecipients = $application->status->docusign_recipient_status ?? [];
                        
                        // Merge new recipients with existing ones, keeping the "best" status
                        foreach ($recipients as $newRecipient) {
                            $found = false;
                            foreach ($currentRecipients as $index => $existingRecipient) {
                                if ($existingRecipient['email'] === $newRecipient['email']) {
                                    // Update existing recipient - keep completed/signed status
                                    // Only update if new status is "better" than existing
                                    $existingStatus = $existingRecipient['status'] ?? 'sent';
                                    $newStatus = $newRecipient['status'];
                                    
                                    // Status hierarchy: completed > signed > delivered > sent
                                    $statusHierarchy = [
                                        'sent' => 1,
                                        'delivered' => 2,
                                        'signed' => 3,
                                        'completed' => 4,
                                    ];
                                    
                                    $existingLevel = $statusHierarchy[$existingStatus] ?? 0;
                                    $newLevel = $statusHierarchy[$newStatus] ?? 0;
                                    
                                    // Only update if new status is higher level OR if it's the same level but has new timestamps
                                    if ($newLevel > $existingLevel || 
                                        ($newLevel === $existingLevel && !empty($newRecipient['signed_at']) && empty($existingRecipient['signed_at']))) {
                                        $currentRecipients[$index] = $newRecipient;
                                    }
                                    // Otherwise keep existing (completed) status
                                    
                                    $found = true;
                                    break;
                                }
                            }
                            
                            // Add new recipient if not found
                            if (!$found) {
                                $currentRecipients[] = $newRecipient;
                            }
                        }
                        
                        $application->status->update([
                            'docusign_recipient_status' => $currentRecipients
                        ]);
                        Log::info('Updated application status with merged recipients');
                        
                        // Check if ALL recipients have signed (simplified - just check every recipient)
                        $allSigned = collect($currentRecipients)->every(fn($r) => in_array($r['status'], ['completed', 'signed']));
                        
                        Log::info('Checked if all signed', [
                            'all_signed' => $allSigned,
                            'recipient_count' => count($currentRecipients),
                            'recipients_detail' => collect($currentRecipients)->map(fn($r) => [
                                'name' => $r['name'] ?? 'Unknown',
                                'email' => $r['email'] ?? 'Unknown',
                                'status' => $r['status'] ?? 'Unknown',
                                'signed_at' => $r['signed_at'] ?? null
                            ])->toArray()
                        ]);
                
                        // Check for director signature to send merchant email
                        $directorSigned = false;
                        $merchantEmail = null;
                        $merchantHasSigned = false;
                        
                        foreach ($currentRecipients as $recipient) {
                            // Find the director (the user who created the application)
                            if ($recipient['email'] === $application->user->email) {
                                if (in_array($recipient['status'], ['completed', 'signed'])) {
                                    $directorSigned = true;
                                }
                            }
                            
                            // Find the merchant (the account holder)
                            if ($recipient['email'] === $application->account->email) {
                                $merchantEmail = $recipient['email'];
                                if (in_array($recipient['status'], ['completed', 'signed'])) {
                                    $merchantHasSigned = true;
                                }
                            }
                        }
                        
                        // If director just signed and merchant hasn't signed yet, send email to merchant
                        if ($directorSigned && !$merchantHasSigned && $merchantEmail) {
                            try {
                                $merchantSigningUrl = $this->docuSignService->getRecipientView(
                                    $this->docuSignService->getAccessToken(),
                                    $envelopeId,
                                    $application->account->email,
                                    $application->account->name ?? $application->trading_name ?? $application->account->email,
                                    'merchant-' . $application->id,
                                    route('applications.docusign-callback', ['application' => $application->id])
                                );
                
                                // Transition to contract_sent if not already there
                                if ($application->status->current_step !== 'contract_sent') {
                                    $application->status->transitionTo(
                                        'contract_sent',
                                        'Contract reminder sent - marked as sent'
                                    );
                                }
                
                                // Fire event to send email to merchant
                                event(new \App\Events\DirectorSignedContractEvent($application, $merchantSigningUrl));
                
                                Log::info('Director signed - merchant email sent', [
                                    'application_id' => $application->id,
                                    'merchant_email' => $merchantEmail,
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Failed to get merchant signing URL', [
                                    'application_id' => $application->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                        
                        // If ALL recipients have signed, transition to contract_signed
                        if ($allSigned && count($currentRecipients) > 0) {
                            Log::info('ALL RECIPIENTS HAVE SIGNED - Transitioning to contract_signed', [
                                'application_id' => $application->id,
                                'current_step' => $application->status->current_step
                            ]);
                            
                            $document->update([
                                'status' => 'completed',
                                'completed_at' => now()
                            ]);
                            
                            $application->status->update([
                                'contract_signed_at' => now()
                            ]);
                            
                            // Only transition if not already past contract_signed
                            if (!in_array($application->status->current_step, ['contract_signed', 'documents_uploaded', 'documents_approved'])) {
                                $application->status->transitionTo(
                                    'contract_signed',
                                    'All parties have signed the contract'
                                );
                                
                                Log::info('Successfully transitioned to contract_signed');
                                
                            } else {
                                Log::info('Already past contract_signed, not transitioning', [
                                    'current_step' => $application->status->current_step
                                ]);
                            }
                        } else {
                            Log::info('Not all recipients have signed yet', [
                                'all_signed' => $allSigned,
                                'recipient_count' => count($currentRecipients),
                                'unsigned_recipients' => collect($currentRecipients)
                                    ->filter(fn($r) => !in_array($r['status'], ['completed', 'signed']))
                                    ->map(fn($r) => [
                                        'name' => $r['name'] ?? 'Unknown',
                                        'email' => $r['email'] ?? 'Unknown',
                                        'status' => $r['status'] ?? 'Unknown'
                                    ])
                                    ->toArray()
                            ]);
                        }
                        
                        event(new \App\Events\DocuSignStatusChangeEvent(
                            $application,
                            'signed',
                            'Recipient has signed the contract'
                        ));
                        
                        Log::info('Processed recipient-completed event');
                    } catch (\Exception $e) {
                        Log::error('Error in recipient-completed handler', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                    break;

                case 'recipient-signed':
                    Log::info('Recipient signed, checking if all complete');
                    
                    event(new \App\Events\DocuSignStatusChangeEvent(
                        $application,
                        'signed',
                        'Recipient has signed the contract'
                    ));
                    
                    Log::info('Processed recipient-signed event');
                    break;
            
                case 'recipient-sent':
                    // Just log it, envelope-sent will handle the main update
                    Log::info('Individual recipient sent notification received');
                    break;

                case 'envelope-completed':
                    $document->update([
                        'status' => 'completed',
                        'completed_at' => now()
                    ]);
                    
                    $application->status->update([
                        'contract_signed_at' => now()
                    ]);
                    
                    $application->status->transitionTo(
                        'contract_signed',
                        'Contract signed via DocuSign'
                    );
            
                    event(new \App\Events\DocuSignStatusChangeEvent(
                        $application,
                        'completed',
                        'All parties have signed the contract'
                    ));

                    Log::info('Processed envelope-completed event');
                    break;
            
                case 'envelope-declined':
                case 'envelope-voided':
                    $document->update(['status' => 'declined']);
                    
                    event(new \App\Events\DocuSignStatusChangeEvent(
                        $application,
                        'declined',
                        'Contract was declined or voided'
                    ));
                    
                    Log::info('Processed envelope-declined/voided event');
                    break;
            
                default:
                    Log::info('Unhandled webhook event', ['event' => $event]);
            }
    
            return response()->noContent();
            
        } catch (\Exception $e) {
            Log::error('DocuSign merchant webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Extract and format recipient status from DocuSign payload
     */
    private function extractRecipientStatus(array $recipients): array
    {
        $status = [];

        foreach (['signers', 'carbonCopies', 'certifiedDeliveries'] as $type) {
            if (isset($recipients[$type])) {
                foreach ($recipients[$type] as $recipient) {
                    $status[] = [
                        'type' => $type,
                        'name' => $recipient['name'] ?? '',
                        'email' => $recipient['email'] ?? '',
                        'status' => $recipient['status'] ?? '',
                        'signed_at' => $recipient['signedDateTime'] ?? null,
                        'delivered_at' => $recipient['deliveredDateTime'] ?? null,
                    ];
                }
            }
        }

        return $status;
    }

    /**
     * Check if all required signers have signed
     */
    private function checkAllRecipientsSigned(array $recipientStatus): bool
    {
        $signers = array_filter($recipientStatus, fn($r) => $r['type'] === 'signers');
        
        foreach ($signers as $signer) {
            if (!in_array($signer['status'], ['completed', 'signed'])) {
                return false;
            }
        }

        return count($signers) > 0;
    }

    /**
     * Handle CardStream gateway contract webhook
     */
    public function handleCardStreamWebhook(Request $request): Response
    {
        try {
            $payload = $request->all();
            
            Log::info('DocuSign CardStream webhook received', [
                'payload' => $payload
            ]);

            // Similar handling as merchant webhook but for gateway contracts
            $envelopeId = $payload['data']['envelopeSummary']['envelopeId'] 
                ?? $payload['envelopeId'] 
                ?? null;
            
            if (!$envelopeId) {
                return response()->noContent();
            }

            $document = ApplicationDocument::where('external_id', $envelopeId)
                ->where('external_system', 'docusign')
                ->where('document_type', 'gateway_contract')
                ->first();

            if (!$document) {
                Log::warning('DocuSign CardStream webhook: document not found', [
                    'envelope_id' => $envelopeId
                ]);
                return response()->noContent();
            }

            $envelopeStatus = $payload['data']['envelopeSummary']['status'] ?? null;

            if ($envelopeStatus === 'completed') {
                $document->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
                
                $document->application->status->transitionTo(
                    'gateway_contract_signed',
                    'CardStream gateway contract signed via DocuSign'
                );
            }

            return response()->noContent();
            
        } catch (\Exception $e) {
            Log::error('DocuSign CardStream webhook error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}