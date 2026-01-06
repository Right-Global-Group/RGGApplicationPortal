<?php

namespace App\Listeners;

use App\Events\DocumentsDumpedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendDocumentsDumpedEmail
{
    public function handle(DocumentsDumpedEvent $event): void
    {
        $application = $event->application;
        $user = $application->user; // The user who created the application

        Log::info('SendDocumentsDumpedEmail triggered', [
            'application_id' => $application->id,
            'user_id' => $user?->id,
            'document_count' => count($event->dumpedDocuments),
        ]);

        if (!$user || !$user->email) {
            Log::warning('User or email missing in SendDocumentsDumpedEmail', [
                'application_id' => $application->id,
            ]);
            return;
        }

        // Send email notification to USER (not account)
        Mail::to($user->email)->send(new DynamicEmail('documents_dumped', [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'application_name' => $application->name,
            'account_name' => $application->account->name ?? 'Unknown',
            'approved_at' => $application->status->application_approved_at?->format('d/m/Y'),
            'dumped_at' => now()->format('d/m/Y H:i'),
            'dumped_documents' => $event->dumpedDocuments,
        ]));

        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'documents_dumped',
            'recipient_email' => $user->email,
            'subject' => 'Application Documents Removed',
            'sent_at' => now(),
        ]);
    }
}