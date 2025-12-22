<?php

namespace App\Listeners;

use App\Events\DocumentUploadReadyEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendDocumentUploadReadyEmail
{
    public function handle(DocumentUploadReadyEvent $event): void
    {
        try {
            $application = $event->application;
            $account = $application->account;

            Log::info('Sending document upload ready email', [
                'application_id' => $application->id,
                'account_email' => $account->email,
                'account_name' => $account->name,
            ]);

            // Generate the upload URL (goes to application edit page with hash anchor to documents)
            $uploadUrl = url("/applications/{$application->id}/edit#documents");

            $emailData = [
                'account_name' => $account->name,
                'application_name' => $application->name,
                'upload_url' => $uploadUrl,
                'application_url' => url("/applications/{$application->id}/edit"),
            ];

            // Send email to merchant account
            Mail::to($account->email)->send(
                new DynamicEmail('document_upload_ready', $emailData)
            );

            // Log the email
            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'document_upload_ready',
                'recipient_email' => $account->email,
                'subject' => 'Contract Signed - Please Upload Your Documents',
                'sent_at' => now(),
            ]);

            Log::info('Document upload ready email sent successfully', [
                'application_id' => $application->id,
                'recipient' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send document upload ready email', [
                'application_id' => $event->application->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}