<?php

namespace App\Listeners;

use App\Events\DocuSignStatusChangeEvent;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;

class SendDocuSignStatusChangeEmail
{
    public function handle(DocuSignStatusChangeEvent $event)
    {
        try {
            $application = $event->application;
            $status = $event->status;
            $statusMessage = $event->message;

            // Prepare email data
            $emailData = [
                'application_name' => $application->name,
                'account_name' => $application->account->name,
                'status' => $status,
                'status_message' => $statusMessage, // ← Changed to 'status_message'
                'timestamp' => now()->format('F j, Y \a\t g:i A'),
                'application_url' => url("/applications/{$application->id}/status"),
            ];

            // Send email to the user who created the application
            $user = $application->account->user;
            
            if ($user && $user->email) {
                Mail::to($user->email)->send(
                    new DynamicEmail('docusign_status_change', $emailData)
                );

                \Log::info('DocuSign status change email sent', [
                    'application_id' => $application->id,
                    'status' => $status,
                    'recipient' => $user->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send DocuSign status change email', [
                'application_id' => $event->application->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}