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
                $account = $application->account;
                $recipientEmail = $account->email;
                $recipientName = $account->name ?? $application->trading_name ?? $account->email;
            } else {
                $user = auth()->guard('web')->user();
                $recipientEmail = $user->email;
                $recipientName = $user->name ?? $user->email;
            }
            
            try {
                $accessToken = $this->getAccessToken();
                
                // Generate new signing URL for existing envelope
                $viewUrl = $this->getRecipientView(
                    $accessToken,
                    $existingEnvelopeId,
                    $recipientEmail,
                    $recipientName,
                    ($isAccount ? 'merchant-' : 'user-') . $application->id,
                    route('applications.docusign-callback', ['application' => $application->id])
                );
                
                return [
                    'envelope_id' => $existingEnvelopeId,
                    'signing_url' => $viewUrl,
                    'embedded_signing' => true,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get signing URL for existing envelope', [
                    'envelope_id' => $existingEnvelopeId,
                    'error' => $e->getMessage(),
                ]);
                
                // If getting view fails, fall through to create new envelope
                Log::info('Creating new envelope because existing one failed');
            }
        }
        
        // CREATE NEW ENVELOPE with BOTH recipients
        $account = $application->account;
        
        if (!$account || !$account->email) {
            throw new \Exception('Account or account email is missing.');
        }
        
        // Determine who is currently logged in (they'll use embedded signing)
        $isAccount = auth()->guard('account')->check();

        if ($isAccount) {
            // Account is clicking - they sign via popup
            $embeddedEmail = $account->email;
            $embeddedName = $account->name ?? $application->trading_name ?? $account->email;
            $embeddedClientId = 'merchant-' . $application->id;
            $embeddedRole = 'Account Merchant'; // Must match DocuSign template exactly
            
            // Portal user gets email
            $user = \App\Models\User::find($application->user_id);
            if (!$user || !$user->email) {
                throw new \Exception('Application user or user email is missing.');
            }
            $emailSignerEmail = $user->email;
            $emailSignerName = $user->name ?? $user->email;
            $emailSignerRole = 'Product Manager'; // Must match DocuSign template exactly
            
        } else {
            // Portal user is clicking - they sign via popup
            $user = auth()->guard('web')->user();
            if (!$user || !$user->email) {
                throw new \Exception('Portal user or user email is missing.');
            }
            $embeddedEmail = $user->email;
            $embeddedName = $user->name ?? $user->email;
            $embeddedClientId = 'user-' . $application->id;
            $embeddedRole = 'Product Manager'; // Must match DocuSign template exactly
            
            // Account gets email
            $emailSignerEmail = $account->email;
            $emailSignerName = $account->name ?? $application->trading_name ?? $account->email;
            $emailSignerRole = 'Account Merchant'; // Must match DocuSign template exactly
        }
        
        try {
            $accessToken = $this->getAccessToken();
            $templateId = '0989e714-d2a3-4a34-a8cb-ae7cf921a865';
        
            // Create envelope with ALL THREE recipients
            $envelopeDefinition = [
                'emailSubject' => "Merchant Application Contract - {$application->name}",
                'templateId' => $templateId,
                'templateRoles' => [
                    [
                        'email' => $embeddedEmail,
                        'name' => $embeddedName,
                        'roleName' => $embeddedRole, // Either 'Account Merchant' or 'Product Manager'
                        'routingOrder' => '1',
                        'clientUserId' => $embeddedClientId, // Enables embedded signing
                    ],
                    [
                        'email' => $emailSignerEmail,
                        'name' => $emailSignerName,
                        'roleName' => $emailSignerRole, // Either 'Product Manager' or 'Account Merchant'
                        'routingOrder' => '1', // Same routing order - can sign in parallel
                    ],
                    [
                        'email' => 'contracts@g2pay.co.uk',
                        'name' => 'G2Pay Director',
                        'roleName' => 'Director', // Must match DocuSign template exactly
                        'routingOrder' => '2', // Signs after the first two complete
                    ],
                ],
                'status' => 'sent',
            ];
    
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
    
            // Get embedded signing URL for current user
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

    /**
     * Send gateway partner contract
     */
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

            $envelopeDefinition = [
                'emailSubject' => "New Merchant Application - {$application->name} - {$partnerConfig['name']}",
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

    private function generateMerchantContractPDF(Application $application): string
    {
        $html = view('pdf.contract', ['application' => $application])->render();
        $pdf = Pdf::loadHTML($html);
        $pdfContent = $pdf->output();
        return base64_encode($pdfContent);
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
        ];
    }
}