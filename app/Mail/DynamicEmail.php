<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DynamicEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected array $subjects = [
        'account_credentials' => 'Your Account Login Credentials',
        'application_created' => 'New Application Created',
        'fees_changed' => 'Application Fees Updated',
        'fees_confirmation_reminder' => 'Action Required: Confirm Your Application Fees',
        'document_uploaded' => 'Document Uploaded to Application',
        'all_documents_uploaded' => 'All Required Documents Uploaded',
        'additional_info_requested' => 'Additional Information Required for Your Application',
        'application_approved' => 'Application Approved',
        // New gateway & WordPress email types
        'merchant_contract_ready' => 'Please Sign Your Merchant Application Contract',
        'gateway_partner_contract_ready' => 'New Merchant Application Contract',
        'wordpress_credentials_request' => 'WordPress Integration Details Needed',
        'wordpress_credentials_reminder' => 'Reminder: WordPress Integration Details Needed',
    ];

    protected array $views = [
        'account_credentials' => 'emails.account-credentials',
        'application_created' => 'emails.application-created',
        'fees_changed' => 'emails.fees-changed',
        'fees_confirmation_reminder' => 'emails.fees-confirmation-reminder',
        'document_uploaded' => 'emails.document-uploaded',
        'all_documents_uploaded' => 'emails.all-documents-uploaded',
        'additional_info_requested' => 'emails.additional-info-requested',
        'application_approved' => 'emails.application-approved',
        // New gateway & WordPress email views
        'merchant_contract_ready' => 'emails.merchant-contract-ready',
        'gateway_partner_contract_ready' => 'emails.gateway-partner-contract-ready',
        'wordpress_credentials_request' => 'emails.wordpress-credentials-request',
        'wordpress_credentials_reminder' => 'emails.wordpress-credentials-reminder',
    ];

    public function __construct(
        public string $emailType,
        public array $data = []
    ) {}

    public function build()
    {
        $subject = $this->subjects[$this->emailType] ?? 'Notification';
        $view = $this->views[$this->emailType] ?? null;

        // Check if view exists
        if (!$view || !view()->exists($view)) {
            \Log::error("Email view not found", [
                'email_type' => $this->emailType,
                'view' => $view,
                'available_views' => array_keys($this->views),
            ]);
            
            throw new \Exception("Email template not found for type: {$this->emailType}");
        }

        return $this->subject($subject)
            ->view($view)
            ->with($this->data);
    }
}