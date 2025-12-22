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
            Log::info('Using existing envelope', ['envelope_id' => $existingEnvelopeId]);
            
            try {
                $accessToken = $this->getAccessToken();
                
                // Get envelope status to check if it's still editable
                $envelopeResponse = Http::withToken($accessToken)
                    ->get("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$existingEnvelopeId}");
                
                if ($envelopeResponse->failed()) {
                    throw new \Exception('Failed to get envelope status');
                }
                
                $envelopeStatus = $envelopeResponse->json();
                
                Log::info('Existing envelope status', [
                    'envelope_id' => $existingEnvelopeId,
                    'status' => $envelopeStatus['status'] ?? 'unknown',
                ]);
                
                // Check envelope status
                $status = $envelopeStatus['status'] ?? null;
                
                // If envelope is completed/voided/declined, create a new one
                if (in_array($status, ['completed', 'voided', 'declined'])) {
                    Log::info('Envelope is already completed/voided, creating new one');
                    
                    $application->status->update([
                        'docusign_envelope_id' => null,
                    ]);
                    
                    // Fall through to create new envelope
                    $existingEnvelopeId = null;
                } else {
                    // Envelope is still active (sent/delivered) - generate signing URL
                    $isAccount = auth()->guard('account')->check();
                    
                    if ($isAccount) {
                        // MERCHANT reopening
                        $envelopeResponse = Http::withToken($accessToken)
                            ->get("{$this->baseUrl}/v2.1/accounts/{$this->accountId}/envelopes/{$existingEnvelopeId}/recipients");
                        
                        if ($envelopeResponse->failed()) {
                            throw new \Exception('Failed to get envelope recipients');
                        }
                        
                        $envelopeData = $envelopeResponse->json();
                        $currentRoutingOrder = $envelopeData['currentRoutingOrder'] ?? 1;
                        
                        $account = $application->account;
                        $merchantSigner = null;
                        $merchantRoutingOrder = null;
                        
                        // Find merchant signer
                        foreach ($envelopeData['signers'] ?? [] as $signer) {
                            if (strtolower($signer['email']) === strtolower($account->email)) {
                                $merchantSigner = $signer;
                                $merchantRoutingOrder = (int)$signer['routingOrder'];
                                break;
                            }
                        }
                        
                        // If not found, try elimination method
                        if (!$merchantSigner) {
                            foreach ($envelopeData['signers'] ?? [] as $signer) {
                                $signerEmail = strtolower($signer['email']);
                                
                                if (stripos($signerEmail, 'g2pay.co.uk') === false && 
                                    stripos($signerEmail, 'management@') === false &&
                                    stripos($signer['roleName'] ?? '', 'Director') === false &&
                                    stripos($signer['roleName'] ?? '', 'Product Manager') === false) {
                                    
                                    $merchantSigner = $signer;
                                    $merchantRoutingOrder = (int)$signer['routingOrder'];
                                    break;
                                }
                            }
                        }
                        
                        if (!$merchantSigner) {
                            throw new \Exception('Merchant recipient not found in envelope');
                        }
                        
                        if ($merchantRoutingOrder > $currentRoutingOrder) {
                            throw new \Exception('The contract is not ready for your signature yet.');
                        }
                        
                        if (in_array($merchantSigner['status'], ['completed', 'signed'])) {
                            throw new \Exception('You have already signed this contract.');
                        }
                        
                        $recipientEmail = $merchantSigner['email'];
                        $recipientName = $merchantSigner['name'];
                        $hasClientUserId = !empty($merchantSigner['clientUserId']);
                        $clientUserId = $hasClientUserId ? $merchantSigner['clientUserId'] : null;
                        
                    } else {
                        // USER reopening
                        $user = auth()->guard('web')->user();
                        $recipientEmail = $user->email;
                        $recipientName = $user->name ?? $user->email;
                        $clientUserId = null;
                    }
                    
                    // Generate signing URL for existing envelope
                    $viewUrl = $this->getRecipientView(
                        $accessToken,
                        $existingEnvelopeId,
                        $recipientEmail,
                        $recipientName,
                        $clientUserId,
                        route('applications.docusign-callback', ['application' => $application->id])
                    );
                    
                    Log::info('Successfully generated signing URL for existing envelope', [
                        'envelope_id' => $existingEnvelopeId,
                        'recipient_email' => $recipientEmail,
                    ]);
                    
                    return [
                        'envelope_id' => $existingEnvelopeId,
                        'signing_url' => $viewUrl,
                        'embedded_signing' => true,
                    ];
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to access existing envelope', [
                    'envelope_id' => $existingEnvelopeId,
                    'error' => $e->getMessage(),
                ]);
                
                // If we can't access it, clear it and create new
                $application->status->update([
                    'docusign_envelope_id' => null,
                ]);
                
                $existingEnvelopeId = null;
            }
        }

        if (!$existingEnvelopeId && $application->status->contract_sent_at) {
            Log::warning('Contract marked as sent but no envelope ID - clearing contract_sent_at', [
                'application_id' => $application->id,
            ]);
            
            // Clear the contract_sent_at timestamp so we can create a fresh envelope
            $application->status->update([
                'contract_sent_at' => null,
            ]);
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
            $templateId = '3e6ada78-333f-412e-830b-203f75fa9644';
        
            // Define tabs that will be applied to ALL recipients
            // These are locked text fields that display pre-filled fee information
            $tabsForAllRecipients = [
                'textTabs' => [
                    // Merchant name on page 1 - FIRST OCCURRENCE (before "incorporated")
                    [
                        'documentId' => '1',
                        'anchorString' => 'incorporated in England and Wales, with registered address as',
                        'anchorXOffset' => '-130',
                        'anchorYOffset' => '-5',  // Changed from '0' to '-3'
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'width' => '190',
                        'height' => '15',
                        'value' => $application->account->name ?? $application->trading_name,
                        'font' => 'Arial',
                        'fontSize' => 'Size8',
                        'tabLabel' => 'merchant_name_before_incorporated',
                        'bold' => 'true',
                        'required' => false,
                        'locked' => true,
                    ],
                    
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
                    
                    // Service fee/monthly minimum
                    [
                        'tabLabel' => 'service_fee_monthly_minimum',
                        'documentId' => '1',
                        'anchorString' => 'Service fee/monthly minimum',
                        'anchorXOffset' => '310',
                        'anchorYOffset' => '-5',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'anchorMatchWholeWord' => 'true',
                        'width' => '350',
                        'height' => '15',
                        'value' => $application->scaling_fee > 0 
                            ? '£' . number_format($application->monthly_minimum, 2) . ' first month. £' . number_format($application->scaling_fee, 2) . ' thereafter'
                            : '£' . number_format($application->monthly_minimum, 2),
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size7',
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
                        'anchorYOffset' => '16',
                        'anchorUnits' => 'pixels',
                        'anchorIgnoreIfNotPresent' => 'false',
                        'width' => '190',
                        'height' => '15',
                        'value' => $application->account->name ?? $application->trading_name,
                        'locked' => true,
                        'font' => 'Arial',
                        'fontSize' => 'Size8',
                        'tabLabel' => 'merchant_name_as_herein',
                        'bold' => 'true',
                    ],
                    
                    // Registered company name - Doc 2 page 1 field
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

            // Second doc form fields - OPTIONAL for User, REQUIRED for Merchant
            $fillableFormTabs = [
                'textTabs' => [

                    // Doc 1 Address
                    [
                        'documentId' => '1',
                        'anchorString' => 'as hereinafter referred to as',
                        'anchorXOffset' => '-250',
                        'anchorYOffset' => '0',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_registration_number',
                    ],

                    // Registration Number
                    [
                        'documentId' => '2',
                        'anchorString' => '2. REGISTRATION NUMBER',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '13',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_registration_number',
                    ],
                    
                    // Registered Address - Street (use full section header)
                    [
                        'documentId' => '2',
                        'anchorString' => '3. REGISTERED ADDRESS*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '27',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_street',
                    ],
                    
                    // City (under Registered Address)
                    [
                        'documentId' => '2',
                        'anchorString' => '3. REGISTERED ADDRESS*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '63',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_city',
                    ],
                    
                    // Country (under Registered Address)
                    [
                        'documentId' => '2',
                        'anchorString' => '3. REGISTERED ADDRESS*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '95',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_country',
                    ],
                    
                    // Postal Code (under Registered Address)
                    [
                        'documentId' => '2',
                        'anchorString' => '3. REGISTERED ADDRESS*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '129',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_postal_code',
                    ],
                    
                    // Trading Address - Street
                    [
                        'documentId' => '2',
                        'anchorString' => '4. TRADING ADDRESS* (CHECK HERE IF SAME AS ABOVE)',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '24',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_trading_street',
                    ],
                    
                    // Trading City
                    [
                        'documentId' => '2',
                        'anchorString' => '4. TRADING ADDRESS* (CHECK HERE IF SAME AS ABOVE)',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '60',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_trading_city',
                    ],
                    
                    // Trading Country
                    [
                        'documentId' => '2',
                        'anchorString' => '4. TRADING ADDRESS* (CHECK HERE IF SAME AS ABOVE)',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '94',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_trading_country',
                    ],
                    
                    // Trading Postal Code
                    [
                        'documentId' => '2',
                        'anchorString' => '4. TRADING ADDRESS* (CHECK HERE IF SAME AS ABOVE)',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '125',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_trading_postal_code',
                    ],
                    
                    // Business Type
                    [
                        'documentId' => '2',
                        'anchorString' => '5. BUSINESS & INDUSTRY TYPE*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '39',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '60',
                        'required' => false,
                        'tabLabel' => 'form_business_type',
                    ],
                    
                    // URL
                    [
                        'documentId' => '2',
                        'anchorString' => '6. URL/S*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '15',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '60',
                        'required' => false,
                        'tabLabel' => 'form_urls',
                    ],
                    
                    // Processing Traffic
                    [
                        'documentId' => '2',
                        'anchorString' => '7. PROCESSING TRAFFIC - COUNTRIES WHERE',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '29',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '60',
                        'required' => false,
                        'tabLabel' => 'form_processing_traffic',
                    ],
                    
                    // Account Country
                    [
                        'documentId' => '2',
                        'anchorString' => '8. ACCOUNT COUNTRY*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '12',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_account_country',
                    ],
                    
                    // Director/Shareholder Location
                    [
                        'documentId' => '2',
                        'anchorString' => '9. DIRECTOR/SHAREHOLDER LOCATION (COUNTRY)*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '13',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_director_location',
                    ],

                    // Accepting Card Payments - If Yes
                    [
                        'documentId' => '2',
                        'anchorString' => 'IF YES PLEASE PROVIDE THE NAME OF THE PROVIDER.',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '13',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_provider_name',
                    ],
                    
                    // Projected Annual Turnover
                    [
                        'documentId' => '2',
                        'anchorString' => '12. PROJECTED ANNUAL TURNOVER',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '17',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_annual_turnover',
                    ],
                    
                    // Average Transaction Value
                    [
                        'documentId' => '2',
                        'anchorString' => '13. AVERAGE TRANSACTION VALUE',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '16',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_avg_transaction',
                    ],
                    
                    // Chargebacks - Percentage
                    [
                        'documentId' => '2',
                        'anchorString' => '14. CHARGEBACKS',
                        'anchorXOffset' => '20',
                        'anchorYOffset' => '15',
                        'anchorUnits' => 'pixels',
                        'width' => '80',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_chargebacks_percentage',
                    ],
                    
                    // Chargebacks - Value
                    [
                        'documentId' => '2',
                        'anchorString' => '14. CHARGEBACKS',
                        'anchorXOffset' => '160',
                        'anchorYOffset' => '12',
                        'anchorUnits' => 'pixels',
                        'width' => '80',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_chargebacks_value',
                    ],
                    
                    // Refunds - Percentage
                    [
                        'documentId' => '2',
                        'anchorString' => '15. REFUNDS',
                        'anchorXOffset' => '20',
                        'anchorYOffset' => '15',
                        'anchorUnits' => 'pixels',
                        'width' => '80',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_refunds_percentage',
                    ],
                    
                    // Refunds - Value
                    [
                        'documentId' => '2',
                        'anchorString' => '15. REFUNDS',
                        'anchorXOffset' => '160',
                        'anchorYOffset' => '17',
                        'anchorUnits' => 'pixels',
                        'width' => '80',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_refunds_value',
                    ],
                    
                    // Delivery Delay
                    [
                        'documentId' => '2',
                        'anchorString' => '16. WHAT IS THE PROJECTED DELIVERY DELAY',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '15',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_delivery_delay',
                    ],
                    
                    // License Details
                    [
                        'documentId' => '2',
                        'anchorString' => 'IF YES PLEASE GIVE DETAILS TO THE LICENSE HELD',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '25',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '60',
                        'required' => false,
                        'tabLabel' => 'form_license_details',
                    ],
                    
                    // Form Creation Date
                    [
                        'documentId' => '2',
                        'anchorString' => 'FORM CREATION DATE*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '14',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_creation_date',
                    ],
                    
                    // Partner Name
                    [
                        'documentId' => '2',
                        'anchorString' => 'PARTNER NAME*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '14',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_partner_name',
                    ],
                    
                    // Commercial Owner Name
                    [
                        'documentId' => '2',
                        'anchorString' => 'COMMERCIAL OWNER NAME*',
                        'anchorXOffset' => '0',
                        'anchorYOffset' => '14',
                        'anchorUnits' => 'pixels',
                        'width' => '250',
                        'height' => '20',
                        'required' => false,
                        'tabLabel' => 'form_commercial_owner',
                    ],
                ],
                'checkboxTabs' => [
                    // Question 10 - YES
                    [
                        'documentId' => '2',
                        'anchorString' => '10. ARE YOU CURRENTLY ACCEPTING CARD PAYMENTS?',
                        'anchorXOffset' => '-6',
                        'anchorYOffset' => '12',
                        'anchorUnits' => 'pixels',
                        'required' => false,
                        'tabLabel' => 'q10_yes',
                    ],
                    
                    // Question 10 - NO
                    [
                        'documentId' => '2',
                        'anchorString' => '10. ARE YOU CURRENTLY ACCEPTING CARD PAYMENTS?',
                        'anchorXOffset' => '100',
                        'anchorYOffset' => '12',
                        'anchorUnits' => 'pixels',
                        'required' => false,
                        'tabLabel' => 'q10_no',
                    ],
                    
                    // Question 11 - ECOM
                    [
                        'documentId' => '2',
                        'anchorString' => '11. CARD ACCEPTING DETAILS',
                        'anchorXOffset' => '-12',
                        'anchorYOffset' => '12',
                        'anchorUnits' => 'pixels',
                        'required' => false,
                        'tabLabel' => 'q11_ecom',
                    ],
                    
                    // Question 11 - MOTO
                    [
                        'documentId' => '2',
                        'anchorString' => '11. CARD ACCEPTING DETAILS',
                        'anchorXOffset' => '96',
                        'anchorYOffset' => '12',
                        'anchorUnits' => 'pixels',
                        'required' => false,
                        'tabLabel' => 'q11_moto',
                    ],
                    
                    // Question 17 - YES
                    [
                        'documentId' => '2',
                        'anchorString' => '17. IS A LICENSE REQUIRED?',
                        'anchorXOffset' => '-8',
                        'anchorYOffset' => '11',
                        'anchorUnits' => 'pixels',
                        'required' => false,
                        'tabLabel' => 'q17_yes',
                    ],
                    
                    // Question 17 - NO
                    [
                        'documentId' => '2',
                        'anchorString' => '17. IS A LICENSE REQUIRED?',
                        'anchorXOffset' => '98',
                        'anchorYOffset' => '11',
                        'anchorUnits' => 'pixels',
                        'required' => false,
                        'tabLabel' => 'q17_no',
                    ],
                ],
            ];

            // Create REQUIRED version for Merchant (deep copy and modify)
            $fillableFormTabsForMerchant = ['textTabs' => [], 'checkboxTabs' => []];

            // Copy and make text tabs required
            foreach ($fillableFormTabs['textTabs'] as $tab) {
                $requiredTab = $tab;
                $requiredTab['required'] = true; // Required for Merchant
                $fillableFormTabsForMerchant['textTabs'][] = $requiredTab;
            }

            $fillableFormTabsForMerchant['radioGroupTabs'] = [
                // Question 10 - Radio Group
                [
                    'groupName' => 'q10_accepting_payments',
                    'radios' => [
                        [
                            'documentId' => '2',
                            'anchorString' => '10. ARE YOU CURRENTLY ACCEPTING CARD PAYMENTS?',
                            'anchorXOffset' => '-6',
                            'anchorYOffset' => '12',
                            'anchorUnits' => 'pixels',
                            'value' => 'yes',
                            'required' => true,
                        ],
                        [
                            'documentId' => '2',
                            'anchorString' => '10. ARE YOU CURRENTLY ACCEPTING CARD PAYMENTS?',
                            'anchorXOffset' => '100',
                            'anchorYOffset' => '12',
                            'anchorUnits' => 'pixels',
                            'value' => 'no',
                            'required' => true,
                        ],
                    ],
                ],
                
                // Question 11 - Radio Group
                [
                    'groupName' => 'q11_card_details',
                    'radios' => [
                        [
                            'documentId' => '2',
                            'anchorString' => '11. CARD ACCEPTING DETAILS',
                            'anchorXOffset' => '-12',
                            'anchorYOffset' => '12',
                            'anchorUnits' => 'pixels',
                            'value' => 'ecom',
                            'required' => true,
                        ],
                        [
                            'documentId' => '2',
                            'anchorString' => '11. CARD ACCEPTING DETAILS',
                            'anchorXOffset' => '96',
                            'anchorYOffset' => '12',
                            'anchorUnits' => 'pixels',
                            'value' => 'moto',
                            'required' => true,
                        ],
                    ],
                ],
                
                // Question 17 - Radio Group
                [
                    'groupName' => 'q17_license_required',
                    'radios' => [
                        [
                            'documentId' => '2',
                            'anchorString' => '17. IS A LICENSE REQUIRED?',
                            'anchorXOffset' => '-8',
                            'anchorYOffset' => '11',
                            'anchorUnits' => 'pixels',
                            'value' => 'yes',
                            'required' => true,
                        ],
                        [
                            'documentId' => '2',
                            'anchorString' => '17. IS A LICENSE REQUIRED?',
                            'anchorXOffset' => '98',
                            'anchorYOffset' => '11',
                            'anchorUnits' => 'pixels',
                            'value' => 'no',
                            'required' => true,
                        ],
                    ],
                ],
            ];

            // User tabs (optional page 2 fields)
            // User tabs - gets both locked display fields AND editable page 2 fields (optional)
            $userTabs = [
                'textTabs' => array_merge(
                    $tabsForAllRecipients['textTabs'],
                    $fillableFormTabs['textTabs']
                ),
                'checkboxTabs' => $fillableFormTabs['checkboxTabs'] ?? [],
            ];

            // Merchant tabs - signature fields for RIGHT column only (no anchor strings)
            $merchantSignatureTabs = [
                'signHereTabs' => [
                    [
                        'documentId' => '1',
                        'pageNumber' => '13',
                        'xPosition' => '377',
                        'yPosition' => '182',
                        'required' => true,
                        'tabLabel' => 'merchant_signature',
                    ],
                ],
                'textTabs' => [
                    // Name field
                    [
                        'documentId' => '1',
                        'pageNumber' => '13',
                        'xPosition' => '377',
                        'yPosition' => '135',
                        'width' => '150',
                        'height' => '20',
                        'required' => true,
                        'tabLabel' => 'merchant_signer_name',
                    ],
                    // Position field
                    [
                        'documentId' => '1',
                        'pageNumber' => '13',
                        'xPosition' => '377',
                        'yPosition' => '164',
                        'width' => '100',
                        'height' => '20',
                        'required' => true,
                        'tabLabel' => 'merchant_signer_position',
                    ],
                ],
                'dateSignedTabs' => [
                    [
                        'documentId' => '1',
                        'pageNumber' => '13',
                        'xPosition' => '377',
                        'yPosition' => '220',
                        'required' => true,
                        'tabLabel' => 'merchant_signature_date',
                    ],
                ],
            ];

            // Update Merchant tabs to include signature tabs
            $merchantTabs = [
                'textTabs' => array_merge(
                    $fillableFormTabsForMerchant['textTabs'],
                    $merchantSignatureTabs['textTabs']
                ),
                'signHereTabs' => $merchantSignatureTabs['signHereTabs'],
                'dateSignedTabs' => $merchantSignatureTabs['dateSignedTabs'],
                'radioGroupTabs' => $fillableFormTabsForMerchant['radioGroupTabs'],
            ];

            // Merge merchant text tabs from both sources
            $merchantTabs['textTabs'] = array_merge(
                $merchantTabs['textTabs'],
                $merchantSignatureTabs['textTabs'] // Name and Position fields
            );

            // Update the envelope definition
            $envelopeDefinition = [
                'emailSubject' => "Merchant Application Contract - {$application->name}",
                'templateId' => $templateId,
                'templateRoles' => [
                    // USER/PRODUCT MANAGER
                    [
                        'email' => $user->email,
                        'name' => $user->name ?? $user->email,
                        'roleName' => 'Product Manager',
                        'routingOrder' => '1',
                        'clientUserId' => 'user-' . $application->id,
                        'tabs' => $userTabs,
                    ],
                    // MERCHANT - Now includes signature tabs
                    [
                        'email' => $account->email,
                        'name' => $account->name ?? $application->trading_name ?? $account->email,
                        'roleName' => 'Account Merchant',
                        'routingOrder' => '2',
                        'clientUserId' => 'merchant-' . $application->id,
                        'tabs' => $merchantTabs, // Includes signature, name, position, and date
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
                    ['name' => 'setup_fee', 'value' => number_format($application->setup_fee, 2)],
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
            // event(new \App\Events\GatewayPartnerContractReadyEvent($application, $viewUrl));
    
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
        ?string $clientUserId,
        string $returnUrl
    ): string {
        $viewRequest = [
            'returnUrl' => $returnUrl,
            'authenticationMethod' => 'none',
            'email' => $email,
            'userName' => $userName,
        ];
        
        // Only add clientUserId if it's provided (not null)
        if ($clientUserId !== null) {
            $viewRequest['clientUserId'] = $clientUserId;
        }
    
        Log::info('getRecipientView request', [
            'envelope_id' => $envelopeId,
            'email' => $email,
            'has_client_user_id' => $clientUserId !== null,
            'request' => $viewRequest,
        ]);
    
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
                'request' => $viewRequest,
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

    public function getAccessToken(): string
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
            'aud' => 'account.docusign.com',
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
                    'tabLabel' => 'setup_fee',
                    'value' => '£' . number_format($application->setup_fee, 2),
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
