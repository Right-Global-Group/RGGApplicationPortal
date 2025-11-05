<?php

namespace App\Listeners;

use App\Events\CardStreamCredentialsReminderEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCardStreamCredentialsReminderEmail
{
    public function handle(CardStreamCredentialsReminderEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        if (!$account || !$account->email) {
            Log::warning('Cannot send CardStream credentials reminder - account or email missing', [
                'application_id' => $application->id,
            ]);
            return;
        }

        try {
            $emailData = [
                'account_name' => $account->name,
                'username' => $application->cardstream_username,
                'password' => $application->cardstream_password,
                'merchant_id' => $application->cardstream_merchant_id,
                'application_url' => route('applications.status', $application),
            ];

            Mail::to($account->email)->send(
                new DynamicEmail('cardstream_credentials_reminder', $emailData)
            );

            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'cardstream_credentials_reminder',
                'recipient_email' => $account->email,
                'subject' => 'Reminder: Set Up Your CardStream Account',
                'sent_at' => now(),
            ]);

            Log::info('CardStream credentials reminder sent', [
                'application_id' => $application->id,
                'account_email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send CardStream credentials reminder', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}