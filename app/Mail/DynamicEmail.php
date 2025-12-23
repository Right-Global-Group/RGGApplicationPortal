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
        'director_signed_contract' => 'Contract Ready for Your Signature',
        'contract_reminder' => 'Reminder: Contract Awaiting Signature',
        'docusign_status_change' => 'DocuSign Status Update',
        // Gateway & WordPress email types
        'gateway_partner_contract_ready' => 'New Merchant Application Contract',
        'wordpress_credentials_request' => 'WordPress Integration Details Needed',
        'wordpress_credentials_reminder' => 'Reminder: WordPress Integration Details Needed',
        'cardstream_submission' => 'New Application Submission - Ready for Processing',
        'invoice_reminder' => 'Reminder: Create Invoice in Xero',
        'cardstream_credentials' => 'Your CardStream Account is Ready',
        'cardstream_credentials_reminder' => 'Reminder: Set Up Your CardStream Account',
        'document_upload_ready' => 'Please Upload Your Documents',
        'account_live' => 'Congratulations! Your Account is Live',
        'account_message_to_user' => 'Message from Your Merchant Account',
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
        'director_signed_contract' => 'emails.director-signed-contract',
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
        'document_upload_ready' => 'emails.document-upload-ready',
        'account_live' => 'emails.account-live',
        'account_message_to_user' => 'emails.account-message-to-user',
    ];

    public function __construct(
        public string $emailType,
        public array $data = [],
        public array $documentAttachments = []
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

        $email = $this->subject($subject)
            ->view($view)
            ->with($this->data);

        // Attach documents if provided
        foreach ($this->documentAttachments as $attachment) {
            if (isset($attachment['path']) && file_exists($attachment['path'])) {
                $email->attach($attachment['path'], [
                    'as' => $attachment['filename'] ?? basename($attachment['path']),
                    'mime' => $attachment['mime'] ?? 'application/octet-stream',
                ]);
            } else {
                \Log::warning('Attachment file not found', [
                    'path' => $attachment['path'] ?? 'null',
                    'email_type' => $this->emailType,
                ]);
            }
        }

        return $email;
    }
}