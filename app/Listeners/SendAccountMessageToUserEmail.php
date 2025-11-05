<?php

namespace App\Listeners;

use App\Events\AccountMessageToUserEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAccountMessageToUserEmail
{
    public function handle(AccountMessageToUserEvent $event): void
    {
        $application = $event->application;
        $user = $application->user;

        if (!$user || !$user->email) {
            Log::warning('Cannot send account message email - user or email missing', [
                'application_id' => $application->id,
            ]);
            return;
        }

        try {
            $emailData = [
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'account_name' => $application->account->name,
                'application_name' => $application->name,
                'account_message' => $event->message,  // ← Changed from 'message' to 'account_message'
                'application_url' => url("/applications/{$application->id}/status"),
            ];

            Mail::to($user->email)->send(
                new DynamicEmail('account_message_to_user', $emailData)
            );

            // Log the email
            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'account_message_to_user',
                'recipient_email' => $user->email,
                'subject' => 'Message from Account: ' . $application->account->name,
                'sent_at' => now(),
            ]);

            Log::info('Account message to user email sent', [
                'application_id' => $application->id,
                'user_email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send account message to user email', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}