<?php

namespace App\Listeners;

use App\Events\WordPressCredentialsReminderEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWordPressCredentialsReminderEmail
{
    public function handle(WordPressCredentialsReminderEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        if (!$account || !$account->email) {
            Log::warning('Cannot send WordPress credentials reminder email - account or email missing', [
                'application_id' => $application->id,
            ]);
            return;
        }

        try {
            $emailData = [
                'account_name' => $account->name,
                'application_name' => $application->name,
                'application_url' => url("/applications/{$application->id}/status"),
            ];

            Mail::to($account->email)->send(
                new DynamicEmail('wordpress_credentials_reminder', $emailData)
            );

            // Log the email
            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'wordpress_credentials_reminder',
                'recipient_email' => $account->email,
                'subject' => 'Reminder: WordPress Integration Details Needed',
                'sent_at' => now(),
            ]);

            Log::info('WordPress credentials reminder email sent', [
                'application_id' => $application->id,
                'account_email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send WordPress credentials reminder email', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}