<?php

namespace App\Listeners;

use App\Events\FeesConfirmationReminderEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SendFeesConfirmationReminderEmail
{
    public function handle(FeesConfirmationReminderEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        if (!$account || !$account->email) {
            Log::warning('Cannot send fees confirmation reminder - account or email missing', [
                'application_id' => $application->id,
            ]);
            return;
        }

        try {
            $emailData = [
                'account_name' => $account->name,
                'application_name' => $application->name,
                'setup_fee' => $application->setup_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'monthly_fee' => $application->monthly_fee,
                'monthly_minimum' => $application->monthly_minimum,
                'service_fee' => $application->service_fee,
                'application_url' => URL::to("/applications/{$application->id}/status"),
                'tracking_url' => '', // Will be set by the listener after email log creation
            ];

            // Create email log first to get the tracking URL
            $emailLog = EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'fees_confirmation_reminder',
                'recipient_email' => $account->email,
                'subject' => 'Action Required: Confirm Your Application Fees',
                'sent_at' => now(),
            ]);

            Mail::to($account->email)->send(
                new DynamicEmail('fees_confirmation_reminder', $emailData)
            );

            Log::info('Fees confirmation reminder sent', [
                'application_id' => $application->id,
                'account_email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send fees confirmation reminder', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}