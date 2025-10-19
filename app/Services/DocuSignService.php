<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
     * Send contract and return the signing URL to open in new tab
     */
    public function sendContract(Application $application): array
    {
        try {
            $accessToken = $this->getAccessToken();

            // Generate PDF contract
            $pdfBase64 = $this->generateContractPDF($application);

            // Create envelope with embedded signing
            $envelopeDefinition = [
                'emailSubject' => "Merchant Application Contract - {$application->name}",
                'documents' => [
                    [
                        'documentBase64' => $pdfBase64,
                        'name' => 'Merchant Application Contract',
                        'fileExtension' => 'pdf',
                        'documentId' => '1',
                    ],
                ],
                'recipients' => [
                    'signers' => [
                        [
                            'email' => $application->email,
                            'name' => $application->name,
                            'recipientId' => '1',
                            'routingOrder' => '1',
                            'clientUserId' => (string) $application->id, // For embedded signing
                            'tabs' => [
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
                            ],
                        ],
                    ],
                ],
                'status' => 'sent',
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes", $envelopeDefinition);

            if ($response->failed()) {
                Log::error('DocuSign Create Envelope Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'application_id' => $application->id,
                ]);
                throw new \Exception('Failed to create envelope: ' . $response->body());
            }

            $envelopeId = $response->json('envelopeId');

            // Get the recipient view URL (embedded signing URL)
            $viewUrl = $this->getRecipientView($accessToken, $envelopeId, $application);

            // Store document record
            ApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => 'contract',
                'external_id' => $envelopeId,
                'external_system' => 'docusign',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return [
                'envelope_id' => $envelopeId,
                'signing_url' => $viewUrl,
            ];
        } catch (\Exception $e) {
            Log::error('DocuSign Send Contract Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the embedded signing URL
     */
    private function getRecipientView(string $accessToken, string $envelopeId, Application $application): string
    {
        $returnUrl = route('docusign.callback', ['application' => $application->id]);

        $viewRequest = [
            'returnUrl' => $returnUrl,
            'authenticationMethod' => 'none',
            'email' => $application->email,
            'userName' => $application->name,
            'clientUserId' => (string) $application->id,
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

    public function handleWebhook(array $payload): void
    {
        $envelopeId = $payload['data']['envelopeSummary']['envelopeId'] ?? $payload['envelopeId'] ?? null;
        $status = $payload['data']['envelopeSummary']['status'] ?? $payload['status'] ?? null;

        if (!$envelopeId || !$status) {
            Log::warning('DocuSign webhook missing data', ['payload' => $payload]);
            return;
        }

        $document = ApplicationDocument::where('external_id', $envelopeId)->first();

        if (!$document) {
            Log::warning('DocuSign webhook: document not found', ['envelope_id' => $envelopeId]);
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

    /**
     * Get DocuSign access token using JWT
     */
    private function getAccessToken(): string
    {
        try {
            // Check if private key file exists
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

    /**
     * Generate JWT token for authentication
     */
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

    /**
     * Generate a dummy PDF contract
     */
    private function generateContractPDF(Application $application): string
    {
        $html = view('pdf.contract', ['application' => $application])->render();
        
        $pdf = Pdf::loadHTML($html);
        $pdfContent = $pdf->output();
        
        return base64_encode($pdfContent);
    }
}