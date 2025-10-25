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
        'fees_confirmed' => 'Application Fees Confirmed',
        'document_uploaded' => 'Document Uploaded to Application',
        'all_documents_uploaded' => 'All Required Documents Uploaded',
        'additional_info_requested' => 'Additional Information Required for Your Application',
        'application_approved' => 'Application Approved',
    ];

    protected array $views = [
        'account_credentials' => 'emails.account-credentials',
        'application_created' => 'emails.application-created',
        'fees_changed' => 'emails.fees-changed',
        'fees_confirmed' => 'emails.fees-confirmed',
        'document_uploaded' => 'emails.document-uploaded',
        'all_documents_uploaded' => 'emails.all-documents-uploaded',
        'additional_info_requested' => 'emails.additional-info-requested',
        'application_approved' => 'emails.application-approved',
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