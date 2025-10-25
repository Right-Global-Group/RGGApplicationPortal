<?php

namespace App\Listeners;

use App\Events\AdditionalInfoRequestedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAdditionalInfoRequestEmail
{
    public function handle(AdditionalInfoRequestedEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        if (!$account || !$account->email) {
            Log::warning('Cannot send additional info request email - account or email missing', [
                'application_id' => $application->id,
            ]);
            return;
        }

        try {
            $emailData = [
                'account_name' => $account->name,
                'application_name' => $application->name,
                'requested_info' => $event->notes,
                'application_url' => url("/applications/{$application->id}/edit"),
                'user_name' => $application->user 
                    ? ($application->user->first_name . ' ' . $application->user->last_name)
                    : 'Administrator',
            ];

            Mail::to($account->email)->send(
                new DynamicEmail('additional_info_requested', $emailData)
            );

            // Log the email
            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'additional_info_requested',
                'recipient_email' => $account->email,
                'subject' => 'Additional Information Required for Your Application',
                'sent_at' => now(),
            ]);

            Log::info('Additional info request email sent', [
                'application_id' => $application->id,
                'account_email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send additional info request email', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}