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
        // Contract & DocuSign email types
        'merchant_contract_ready' => 'Please Sign Your Merchant Application Contract',
        'contract_reminder' => 'Reminder: Contract Awaiting Signature',
        'docusign_status_change' => 'DocuSign Status Update',
        // Gateway & WordPress email types
        'gateway_partner_contract_ready' => 'New Merchant Application Contract',
        'wordpress_credentials_request' => 'WordPress Integration Details Needed',
        'wordpress_credentials_reminder' => 'Reminder: WordPress Integration Details Needed',
        'cardstream_submission' => 'Email Sent to CardStream with Contract',
        'invoice_reminder' => 'Reminder: Create Invoice in Xero',
        'cardstream_credentials' => 'Your CardStream Account is Ready',
        'cardstream_credentials_reminder' => 'Reminder: Set Up Your CardStream Account',
        'account_live' => 'Congratulations! Your Account is Live',
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
        // Contract & DocuSign email views
        'merchant_contract_ready' => 'emails.merchant-contract-ready',
        'contract_reminder' => 'emails.contract-reminder',
        'docusign_status_change' => 'emails.docusign-status-change',
        // Gateway & WordPress email views
        'gateway_partner_contract_ready' => 'emails.gateway-partner-contract-ready',
        'wordpress_credentials_request' => 'emails.wordpress-credentials-request',
        'wordpress_credentials_reminder' => 'emails.wordpress-credentials-reminder',
        'cardstream_submission' => 'emails.cardstream-submission',
        'invoice_reminder' => 'emails.invoice-reminder',
        'cardstream_credentials' => 'emails.cardstream-credentials',
        'cardstream_credentials_reminder' => 'emails.cardstream-credentials',
        'account_live' => 'emails.account-live',
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