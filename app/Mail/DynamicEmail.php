<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DynamicEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailType;
    public array $data;
    public $entity;

    /**
     * Create a new message instance.
     */
    public function __construct(string $emailType, array $data, $entity = null)
    {
        $this->emailType = $emailType;
        $this->data = $data;
        $this->entity = $entity;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjects = [
            'account_credentials' => 'Your Account Credentials',
            'application_created' => 'New Application Created',
            'fees_confirmed' => 'Application Fees Confirmed',
            'fees_changed' => 'Application Fees Changed - Confirmation Required',
            'wallet_order_confirmed' => 'Wallet Transaction Confirmed',
            'document_uploaded' => 'Document Uploaded to Application',
            'all_documents_uploaded' => 'All Required Documents Uploaded',
        ];

        return new Envelope(
            subject: $subjects[$this->emailType] ?? 'Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $views = [
            'account_credentials' => 'emails.account-credentials',
            'application_created' => 'emails.application-created',
            'fees_confirmed' => 'emails.fees-confirmed',
            'fees_changed' => 'emails.fees-changed',
            'wallet_order_confirmed' => 'emails.wallet-order-confirmed',
            'document_uploaded' => 'emails.document-uploaded',
            'all_documents_uploaded' => 'emails.all-documents-uploaded',
        ];

        return new Content(
            view: $views[$this->emailType] ?? 'emails.default',
            with: $this->data,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}