<?php

namespace App\Listeners;

use App\Events\AllDocumentsUploadedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAllDocumentsUploadedEmail
{
    public function handle(AllDocumentsUploadedEvent $event): void
    {
        $application = $event->application;
        $user = $application->user;

        Log::info('SendAllDocumentsUploadedEmailListener triggered', [
            'application_id' => $application->id,
        ]);

        if (!$user || !$user->email) {
            Log::warning('User or email missing in SendAllDocumentsUploadedEmailListener', [
                'application_id' => $application->id,
            ]);
            return;
        }

        // Get all uploaded documents grouped by category
        $documents = $application->documents()
            ->whereNotNull('document_category')
            ->get()
            ->groupBy('document_category');

        $documentList = [];
        foreach ($documents as $category => $docs) {
            $documentList[] = [
                'category' => \App\Models\ApplicationDocument::getRequiredCategories()[$category] ?? $category,
                'count' => $docs->count(),
            ];
        }

        // Send all documents uploaded notification to user
        Mail::to($user->email)->send(new DynamicEmail('all_documents_uploaded', [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'application_name' => $application->name,
            'account_name' => $application->account->name,
            'documents' => $documentList,
            'application_url' => route('applications.edit', $application),
        ], $application));

        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'all_documents_uploaded',
            'recipient_email' => $user->email,
            'subject' => 'All Required Documents Uploaded',
            'sent_at' => now(),
        ]);

        Log::info('All documents uploaded notification sent to user', [
            'application_id' => $application->id,
            'user_email' => $user->email,
        ]);
    }
}