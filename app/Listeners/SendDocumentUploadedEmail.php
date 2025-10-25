<?php

namespace App\Listeners;

use App\Events\DocumentUploadedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendDocumentUploadedEmail
{
    public function handle(DocumentUploadedEvent $event): void
    {
        $document = $event->document;
        $application = $document->application;
        $user = $application->user;

        // Only send email if uploaded by account (not by user/admin)
        if ($document->uploaded_by_type !== 'account') {
            return;
        }

        Log::info('SendDocumentUploadedEmailListener triggered', [
            'document_id' => $document->id,
            'application_id' => $application->id,
            'category' => $document->document_category,
        ]);

        if (!$user || !$user->email) {
            Log::warning('User or email missing in SendDocumentUploadedEmailListener', [
                'application_id' => $application->id,
            ]);
            return;
        }

        // Send document uploaded notification to user
        Mail::to($user->email)->send(new DynamicEmail('document_uploaded', [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'application_name' => $application->name,
            'account_name' => $application->account->name,
            'document_category' => $document->category_name,
            'uploaded_at' => $document->created_at->format('d/m/Y H:i'),
            'application_url' => route('applications.edit', $application),
        ], $application));

        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'document_uploaded',
            'recipient_email' => $user->email,
            'subject' => 'Document Uploaded to Application',
            'sent_at' => now(),
        ]);

        Log::info('Document uploaded notification sent to user', [
            'document_id' => $document->id,
            'user_email' => $user->email,
        ]);
    }
}