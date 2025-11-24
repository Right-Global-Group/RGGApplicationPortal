<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\MerchantContractReady;
use App\Mail\GatewayPartnerContractReady;

class DocuSignService
{
    private string $baseUrl;
    private string $accountId;
    private string $integrationKey;
    private string $userId;
    private string $privateKey;
    private string $authUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.docusign.base_url');
        $this->accountId = config('services.docusign.account_id');
        $this->integrationKey = config('services.docusign.integration_key');
        $this->userId = config('services.docusign.user_id');
        $this->privateKey = storage_path('app/docusign/private.key');
        $this->authUrl = config('services.docusign.auth_url', 'https://account-d.docusign.com');
    }

    /**
     * Get DocuSign contract and return the signing URL
     */
    public function sendDocuSignContract(Application $application): array
    {
        // Does this application already have an active envelope?
        $existingEnvelopeId = $application->status->docusign_envelope_id;
        
        if ($existingEnvelopeId) {
            // Envelope already exists - just generate a new signing URL for current user
            Log::info('Using existing envelope', ['envelope_id' => $existingEnvelopeId]);
            
            // Determine who is currently logged in
            $isAccount = auth()->guard('account')->check();
            
            if ($isAccount) {
                // MERCHANT is trying to sign - check if all previous routing orders are complete
                try {
                    $accessToken = $this->getAccessToken();
                    
                    // Get envelope details including current routing order
                    $envelopeResponse = Http::withToken($accessToken)
                        ->get("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$existingEnvelopeId}/recipients");
                    
                    if ($envelopeResponse->failed()) {
                        throw new \Exception('Failed to get envelope recipients');
                    }
                    
                    $envelopeData = $envelopeResponse->json();
                    $currentRoutingOrder = $envelopeData['currentRoutingOrder'] ?? 1;
                    
                    Log::info('Checking merchant signing eligibility', [
                        'current_routing_order' => $currentRoutingOrder,
                        'envelope_data' => $envelopeData,
                    ]);
                    
                    // Get the merchant's routing order
                    $account = $application->account;
                    $merchantRoutingOrder = null;
                    
                    foreach ($envelopeData['signers'] ?? [] as $signer) {
                        if ($signer['email'] === $account->email) {
                            $merchantRoutingOrder = (int)$signer['routingOrder'];
                            break;
                        }
                    }
                    
                    if ($merchantRoutingOrder === null) {
                        throw new \Exception('Merchant recipient not found in envelope');
                    }
                    
                    // Check if merchant's turn to sign
                    if ($merchantRoutingOrder > $currentRoutingOrder) {
                        throw new \Exception('The contract is not ready for your signature yet. Previous signers must complete their review first. Please wait for an email notification when it\'s your turn to sign.');
                    }
                    
                    $recipientEmail = $account->email;
                    $recipientName = $account->name ?? $application->trading_name ?? $account->email;
                    $clientUserId = 'merchant-' . $application->id;
                    
                } catch (\Exception $e) {
                    Log::error('Failed to check merchant signing eligibility', [
                        'envelope_id' => $existingEnvelopeId,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            } else {
                // USER is opening
                $accessToken = $this->getAccessToken();
                $user = auth()->guard('web')->user();
                $recipientEmail = $user->email;
                $recipientName = $user->name ?? $user->email;
                $clientUserId = 'user-' . $application->id;
            }
            
            try {
                if (!isset($accessToken)) {
                    $accessToken = $this->getAccessToken();
                }
                
                // Generate new signing URL for existing envelope
                $viewUrl = $this->getRecipientView(
                    $accessToken,
                    $existingEnvelopeId,
                    $recipientEmail,
                    $recipientName,
                    $clientUserId,
                    route('applications.docusign-callback', ['application' => $application->id])
                );
                
                Log::info('Generated signing URL for existing envelope', [
                    'envelope_id' => $existingEnvelopeId,
                    'recipient_email' => $recipientEmail,
                    'client_user_id' => $clientUserId,
                ]);
                
                return [
                    'envelope_id' => $existingEnvelopeId,
                    'signing_url' => $viewUrl,
                    'embedded_signing' => true,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get signing URL for existing envelope', [
                    'envelope_id' => $existingEnvelopeId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                throw $e; // Don't create new envelope, throw error
            }
        
        }
        
        // CREATE NEW ENVELOPE
        $account = $application->account;
        
        if (!$account || !$account->email) {
            throw new \Exception('Account or account email is missing.');
        }
        
        // Get both user and account details
        $user = \App\Models\User::find($application->user_id);
        if (!$user || !$user->email) {
            throw new \Exception('Application user or user email is missing.');
        }
        
        // Determine who is currently logged in
        $isAccount = auth()->guard('account')->check();
    
        if ($isAccount) {
            // If MERCHANT clicks first, they cannot create envelope - user must go first
            throw new \Exception('The contract must be reviewed by the Product Manager before you can sign. Please contact your account manager.');
        }
        
        // Only USER can create the envelope
        $embeddedEmail = $user->email;
        $embeddedName = $user->name ?? $user->email;
        $embeddedClientId = 'user-' . $application->id;
        
        try {
            $accessToken = $this->getAccessToken();
            $templateId = '4247195d-137e-47da-bff6-9fb4d6d7e0a6';
        
            // Define tabs that will be applied to ALL recipients
            // These are locked text fields that display pre-filled fee information
            $tabsForAllRecipients = [
                'textTabs' => [
                    // All Request Types - Fixed fee
                    [
                        'documentId' => '1',
                        'anchorString' => 'All request types',
                        'anchorXOffset' => '313',
                        'anchorYOffset' => '-5',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'anchorMatchWholeWord' => 'true',
                        'width' => '80',
                        'height' => '15',
                        'value' => '£' . number_format($application->transaction_fixed_fee, 2),
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size9',
                        'tabLabel' => 'all_request_types_fee',
                    ],
                    
                    // Monthly Fee (first occurrence)
                    [
                        'documentId' => '1',
                        'anchorString' => 'Monthly Fee',
                        'anchorXOffset' => '313',
                        'anchorYOffset' => '-5',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'width' => '80',
                        'height' => '15',
                        'value' => '£' . number_format($application->monthly_fee, 2),
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size9',
                        'tabLabel' => 'monthly_fee_1',
                    ],
                    
                    // Service fee/monthly minimum
                    [
                        'documentId' => '1',
                        'anchorString' => 'Service fee/monthly minimum',
                        'anchorXOffset' => '310',
                        'anchorYOffset' => '-5',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'anchorMatchWholeWord' => 'true',
                        'width' => '350',
                        'height' => '15',
                        'value' => '£' . number_format($application->monthly_minimum, 2) . ' first month. £' . number_format($application->scaling_fee, 2) . ' thereafter',
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size7',
                        'tabLabel' => 'service_fee_monthly_minimum',
                    ],
                    
                    // Monthly Fee (inc PCI)
                    [
                        'documentId' => '1',
                        'anchorString' => 'Monthly Fee (inc PCI)',
                        'anchorXOffset' => '313',
                        'anchorYOffset' => '-5',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'anchorMatchWholeWord' => 'true',
                        'width' => '80',
                        'height' => '15',
                        'value' => '£' . number_format($application->monthly_fee, 2),
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size9',
                        'tabLabel' => 'monthly_fee_inc_pci',
                    ],
                    
                    // UK Consumer Debit - Percentage
                    [
                        'documentId' => '1',
                        'anchorString' => 'UK Consumer Debit',
                        'anchorXOffset' => '313',
                        'anchorYOffset' => '-5',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'anchorMatchWholeWord' => 'true',
                        'width' => '80',
                        'height' => '15',
                        'value' => number_format($application->transaction_percentage, 2) . '%',
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size9',
                        'tabLabel' => 'uk_debit_percentage',
                    ],
                    
                    // UK Consumer Credit - Percentage
                    [
                        'documentId' => '1',
                        'anchorString' => 'UK Consumer Credit',
                        'anchorXOffset' => '313',
                        'anchorYOffset' => '-5',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'anchorMatchWholeWord' => 'true',
                        'width' => '80',
                        'height' => '15',
                        'value' => number_format($application->transaction_percentage, 2) . '%',
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size9',
                        'tabLabel' => 'uk_credit_percentage',
                    ],
                    
                    // Merchant name - page 16 signature area
                    [
                        'documentId' => '1',
                        'anchorString' => 'Exclusivity Clause',
                        'anchorXOffset' => '140',
                        'anchorYOffset' => '18',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'true',
                        'anchorCaseSensitive' => 'false',
                        'width' => '250',
                        'height' => '15',
                        'value' => $application->account->name ?? $application->trading_name,
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size7',
                        'tabLabel' => 'merchant_name_signature',
                    ],
                    
                    // Registered company name - page 1 field
                    [
                        'documentId' => '1',
                        'anchorString' => 'REGISTERED COMPANY NAME*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '8',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'true',
                        'width' => '200',
                        'height' => '15',
                        'value' => $application->account->name,
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size10',
                        'tabLabel' => 'registered_company_name',
                    ],
                ],
            ];
    
            // SIMPLIFIED: Use templateRoles instead of compositeTemplates
            // This ensures proper role matching with your DocuSign template
            $envelopeDefinition = [
                'emailSubject' => "Merchant Application Contract - {$application->name}",
                'templateId' => $templateId,
                'templateRoles' => [
                    // USER/PRODUCT MANAGER - Gets the pre-filled tabs
                    [
                        'email' => $user->email,
                        'name' => $user->name ?? $user->email,
                        'roleName' => 'Product Manager',
                        'routingOrder' => '1',
                        'clientUserId' => 'user-' . $application->id,
                        'tabs' => $tabsForAllRecipients,  // ← Only here
                    ],
                    // MERCHANT - No tabs needed (inherits from template)
                    [
                        'email' => $account->email,
                        'name' => $account->name ?? $application->trading_name ?? $account->email,
                        'roleName' => 'Account Merchant',
                        'routingOrder' => '2',
                        'clientUserId' => 'merchant-' . $application->id,
                        // 'tabs' => $tabsForAllRecipients,  // ← Remove this line
                    ],
                ],
                'status' => 'sent',
            ];
    
            Log::info('Creating DocuSign envelope', [
                'user_email' => $user->email,
                'merchant_email' => $account->email,
                'application_id' => $application->id,
            ]);

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes", $envelopeDefinition);
    
            if ($response->failed()) {
                Log::error('DocuSign Create Merchant Envelope Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request' => $envelopeDefinition,
                    'application_id' => $application->id,
                ]);
                throw new \Exception('Failed to create envelope: ' . $response->body());
            }
    
            $envelopeId = $response->json('envelopeId');
    
            // Get embedded signing URL for user
            $viewUrl = $this->getRecipientView(
                $accessToken,
                $envelopeId,
                $embeddedEmail,
                $embeddedName,
                $embeddedClientId,
                route('applications.docusign-callback', ['application' => $application->id])
            );
    
            // Store document record
            ApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => 'contract',
                'external_id' => $envelopeId,
                'external_system' => 'docusign',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
    
            // Update status
            $application->status->update([
                'docusign_envelope_id' => $envelopeId,
            ]);
    
            // Fire event to send notification email
            event(new \App\Events\MerchantContractReadyEvent(
                $application, 
                route('applications.status', ['application' => $application->id])
            ));
    
            return [
                'envelope_id' => $envelopeId,
                'signing_url' => $viewUrl,
                'embedded_signing' => true,
            ];
        } catch (\Exception $e) {
            Log::error('DocuSign Send Merchant Contract Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'application_id' => $application->id,
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all recipients for an envelope with their current status
     */
    public function getEnvelopeRecipients(string $envelopeId): array
    {
        try {
            Log::info('Getting envelope recipients', ['envelope_id' => $envelopeId]);
            
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->get("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$envelopeId}/recipients");

            if ($response->failed()) {
                Log::error('DocuSign Get Recipients Error', [
                    'envelope_id' => $envelopeId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to get envelope recipients: ' . $response->body());
            }

            $data = $response->json();
            Log::info('DocuSign recipients response', ['data' => $data]);
            
            $recipients = [];

            // Extract signers
            foreach ($data['signers'] ?? [] as $signer) {
                $recipients[] = [
                    'name' => $signer['name'],
                    'email' => $signer['email'],
                    'status' => strtolower($signer['status']), // sent, delivered, signed, completed
                    'signed_at' => $signer['signedDateTime'] ?? null,
                    'delivered_at' => $signer['deliveredDateTime'] ?? null,
                ];
            }

            Log::info('Extracted recipients', [
                'envelope_id' => $envelopeId,
                'count' => count($recipients),
                'recipients' => $recipients,
            ]);

            return $recipients;
            
        } catch (\Exception $e) {
            Log::error('Failed to get envelope recipients', [
                'envelope_id' => $envelopeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty array so webhook doesn't crash
            return [];
        }
    }

    public function sendGatewayPartnerContract(Application $application): array
    {
        if (!$application->gateway_partner) {
            throw new \Exception('Gateway partner not selected for this application.');
        }
    
        $partnerConfig = config("gateway-partners.{$application->gateway_partner}");
        $recipientEmail = $partnerConfig['contact_email'];
        $recipientName = $partnerConfig['name'] . ' Contracts Team';
    
        try {
            $accessToken = $this->getAccessToken();
            $pdfBase64 = $this->generateGatewayPartnerContractPDF($application);
    
            // Add custom fields for prepopulating data
            $customFields = [
                'textCustomFields' => [
                    ['name' => 'scaling_fee', 'value' => number_format($application->scaling_fee, 2)],
                    ['name' => 'transaction_percentage', 'value' => number_format($application->transaction_percentage, 2)],
                    ['name' => 'transaction_fixed_fee', 'value' => number_format($application->transaction_fixed_fee, 2)],
                    ['name' => 'monthly_fee', 'value' => number_format($application->monthly_fee, 2)],
                    ['name' => 'monthly_minimum', 'value' => number_format($application->monthly_minimum, 2)],
                    ['name' => 'service_fee', 'value' => number_format($application->service_fee, 2)],
                    ['name' => 'merchant_name', 'value' => $application->account->name ?? $application->trading_name],
                    ['name' => 'registered_company_name', 'value' => $application->account->name],
                ]
            ];
    
            $envelopeDefinition = [
                'emailSubject' => "New Merchant Application - {$application->name} - {$partnerConfig['name']}",
                'customFields' => $customFields,
                'documents' => [
                    [
                        'documentBase64' => $pdfBase64,
                        'name' => "{$partnerConfig['name']} Gateway Contract",
                        'fileExtension' => 'pdf',
                        'documentId' => '1',
                    ],
                ],
                'recipients' => [
                    'signers' => [
                        [
                            'email' => $recipientEmail,
                            'name' => $recipientName,
                            'recipientId' => '1',
                            'routingOrder' => '1',
                            'clientUserId' => 'gateway-' . $application->id,
                            'tabs' => $this->getGatewayContractTabs($application),
                        ],
                    ],
                ],
                'status' => 'sent',
            ];
    
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes", $envelopeDefinition);
    
            if ($response->failed()) {
                Log::error('DocuSign Create Gateway Envelope Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'application_id' => $application->id,
                ]);
                throw new \Exception('Failed to create envelope: ' . $response->body());
            }
    
            $envelopeId = $response->json('envelopeId');
            $viewUrl = $this->getRecipientView(
                $accessToken,
                $envelopeId,
                $recipientEmail,
                $recipientName,
                'gateway-' . $application->id,
                route('applications.gateway-docusign-callback', ['application' => $application->id])
            );
    
            // Store document record
            ApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => 'gateway_contract',
                'external_id' => $envelopeId,
                'external_system' => 'docusign',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
    
            // Update status
            $application->status->update([
                'gateway_docusign_envelope_id' => $envelopeId,
            ]);
    
            // Fire event to send email (instead of sending directly)
            event(new \App\Events\GatewayPartnerContractReadyEvent($application, $viewUrl));
    
            return [
                'envelope_id' => $envelopeId,
                'signing_url' => $viewUrl,
            ];
        } catch (\Exception $e) {
            Log::error('DocuSign Send Gateway Contract Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the embedded signing URL
     */
    private function getRecipientView(
        string $accessToken, 
        string $envelopeId, 
        string $email,
        string $userName,
        string $clientUserId,
        string $returnUrl
    ): string {
        $viewRequest = [
            'returnUrl' => $returnUrl,
            'authenticationMethod' => 'none',
            'email' => $email,
            'userName' => $userName,
            'clientUserId' => $clientUserId,
        ];

        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(
                "{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$envelopeId}/views/recipient",
                $viewRequest
            );

        if ($response->failed()) {
            Log::error('DocuSign Get Recipient View Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to get signing URL: ' . $response->body());
        }

        return $response->json('url');
    }

    public function getEnvelopeStatus(string $envelopeId): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$envelopeId}");

        if ($response->failed()) {
            throw new \Exception('Failed to get envelope status');
        }

        return $response->json();
    }


    /**
     * Get download URL for a specific document in an envelope
     * 
     * @param string $envelopeId The envelope ID
     * @param string $documentId The document ID (use '2' for second document in template)
     * @return string The document download URL
     */
    public function getEnvelopeDocumentUrl(string $envelopeId, string $documentId = '2'): string
    {
        try {
            $accessToken = $this->getAccessToken();
            
            // Build the document download URL
            // This URL allows downloading the specific document from the envelope
            $documentUrl = "{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$envelopeId}/documents/{$documentId}";
            
            Log::info('Generated DocuSign document URL', [
                'envelope_id' => $envelopeId,
                'document_id' => $documentId,
                'url' => $documentUrl,
            ]);
            
            return $documentUrl;
            
        } catch (\Exception $e) {
            Log::error('Failed to get document URL', [
                'envelope_id' => $envelopeId,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Download a specific document from an envelope as base64
     * 
     * @param string $envelopeId The envelope ID
     * @param string $documentId The document ID
     * @return string Base64 encoded document content
     */
    public function downloadEnvelopeDocument(string $envelopeId, string $documentId = '2'): string
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withToken($accessToken)
                ->get("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$envelopeId}/documents/{$documentId}");
            
            if ($response->failed()) {
                Log::error('Failed to download document', [
                    'envelope_id' => $envelopeId,
                    'document_id' => $documentId,
                    'status' => $response->status(),
                ]);
                throw new \Exception('Failed to download document from DocuSign');
            }
            
            return base64_encode($response->body());
            
        } catch (\Exception $e) {
            Log::error('Error downloading document', [
                'envelope_id' => $envelopeId,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    public function handleMerchantWebhook(array $payload): void
    {
        $envelopeId = $payload['data']['envelopeSummary']['envelopeId'] ?? $payload['envelopeId'] ?? null;
        $status = $payload['data']['envelopeSummary']['status'] ?? $payload['status'] ?? null;

        if (!$envelopeId || !$status) {
            Log::warning('DocuSign merchant webhook missing data', ['payload' => $payload]);
            return;
        }

        $document = ApplicationDocument::where('external_id', $envelopeId)
            ->where('document_type', 'contract')
            ->first();

        if (!$document) {
            Log::warning('DocuSign merchant webhook: document not found', ['envelope_id' => $envelopeId]);
            return;
        }

        $statusMapping = [
            'completed' => 'completed',
            'declined' => 'declined',
            'voided' => 'declined',
        ];

        $newStatus = $statusMapping[$status] ?? 'sent';
        $document->update(['status' => $newStatus]);

        if ($status === 'completed') {
            $document->update(['completed_at' => now()]);
            $document->application->status->transitionTo('contract_completed', 'Contract signed via DocuSign');
            $document->application->status->transitionTo('contract_submitted', 'Contract automatically submitted');
        }
    }

    public function handleGatewayWebhook(array $payload): void
    {
        $envelopeId = $payload['data']['envelopeSummary']['envelopeId'] ?? $payload['envelopeId'] ?? null;
        $status = $payload['data']['envelopeSummary']['status'] ?? $payload['status'] ?? null;

        if (!$envelopeId || !$status) {
            Log::warning('DocuSign gateway webhook missing data', ['payload' => $payload]);
            return;
        }

        $document = ApplicationDocument::where('external_id', $envelopeId)
            ->where('document_type', 'gateway_contract')
            ->first();

        if (!$document) {
            Log::warning('DocuSign gateway webhook: document not found', ['envelope_id' => $envelopeId]);
            return;
        }

        $statusMapping = [
            'completed' => 'completed',
            'declined' => 'declined',
            'voided' => 'declined',
        ];

        $newStatus = $statusMapping[$status] ?? 'sent';
        $document->update(['status' => $newStatus]);

        if ($status === 'completed') {
            $document->update(['completed_at' => now()]);
            $document->application->status->transitionTo('gateway_contract_signed', 'Gateway contract signed via DocuSign');
        }
    }

    private function getAccessToken(): string
    {
        try {
            if (!file_exists($this->privateKey)) {
                throw new \Exception('DocuSign private key file not found at: ' . $this->privateKey);
            }

            $privateKey = file_get_contents($this->privateKey);
            $jwtToken = $this->generateJWT($privateKey);

            $response = Http::asForm()->post("{$this->authUrl}/oauth/token", [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwtToken,
            ]);

            if ($response->failed()) {
                Log::error('DocuSign Auth Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to authenticate with DocuSign: ' . $response->body());
            }

            return $response->json('access_token');
        } catch (\Exception $e) {
            Log::error('DocuSign GetAccessToken Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function generateJWT(string $privateKey): string
    {
        $now = time();

        $payload = [
            'iss' => $this->integrationKey,
            'sub' => $this->userId,
            'aud' => 'account-d.docusign.com',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'signature impersonation',
        ];

        return JWT::encode($payload, $privateKey, 'RS256');
    }

    private function generateGatewayPartnerContractPDF(Application $application): string
    {
        $template = config("gateway-partners.{$application->gateway_partner}.contract_template");
        $html = view($template, ['application' => $application])->render();
        $pdf = Pdf::loadHTML($html);
        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
    }

    private function getMerchantContractTabs(Application $application): array
    {
        return [
            'signHereTabs' => [
                [
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '100',
                    'yPosition' => '650',
                ],
            ],
            'dateSignedTabs' => [
                [
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '300',
                    'yPosition' => '650',
                ],
            ],
            'textTabs' => [
                [
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '100',
                    'yPosition' => '600',
                    'width' => 200,
                    'height' => 20,
                    'required' => true,
                    'tabLabel' => 'Full Name',
                    'value' => $application->name,
                ],
            ],
        ];
    }

    private function getGatewayContractTabs(Application $application): array
    {
        return [
            'textTabs' => [
                [
                    'tabLabel' => 'merchant_name',
                    'value' => $application->account->name ?? $application->trading_name,
                    'locked' => true,
                    'required' => true,
                ],
                [
                    'tabLabel' => 'registered_company_name',
                    'value' => $application->account->name,
                    'locked' => true,
                    'required' => true,
                ],
                [
                    'tabLabel' => 'scaling_fee',
                    'value' => '£' . number_format($application->scaling_fee, 2),
                    'locked' => true,
                ],
                [
                    'tabLabel' => 'transaction_percentage',
                    'value' => number_format($application->transaction_percentage, 2) . '%',
                    'locked' => true,
                ],
                [
                    'tabLabel' => 'transaction_fixed_fee',
                    'value' => '£' . number_format($application->transaction_fixed_fee, 2),
                    'locked' => true,
                ],
                [
                    'tabLabel' => 'monthly_fee',
                    'value' => '£' . number_format($application->monthly_fee, 2),
                    'locked' => true,
                ],
                [
                    'tabLabel' => 'monthly_minimum',
                    'value' => '£' . number_format($application->monthly_minimum, 2),
                    'locked' => true,
                ],
                [
                    'tabLabel' => 'service_fee',
                    'value' => '£' . number_format($application->service_fee, 2),
                    'locked' => true,
                ],
            ],
            'signHereTabs' => [
                [
                    'tabLabel' => 'Gateway Partner Signature',
                    'required' => true,
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '100',
                    'yPosition' => '650',
                ],
            ],
            'dateSignedTabs' => [
                [
                    'tabLabel' => 'Date Signed',
                    'required' => true,
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '300',
                    'yPosition' => '650',
                ],
            ],
        ];
    }
}