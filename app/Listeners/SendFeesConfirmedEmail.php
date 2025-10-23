<?php

namespace App\Listeners;

use App\Events\FeesConfirmedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendFeesConfirmedEmail
{
    public function handle(FeesConfirmedEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        Log::info('SendFeesConfirmedEmail triggered', [
            'application_id' => $application->id,
            'account_id' => $account->id,
            'account_email' => $account->email,
        ]);

        if (!$account || !$account->email) {
            Log::warning('Account or email missing in SendFeesConfirmedEmail', [
                'application_id' => $application->id,
            ]);
            return;
        }

        // Notify the admin/user who created the application
        if ($application->user && $application->user->email) {
            Mail::to($application->user->email)->send(new DynamicEmail('fees_confirmed', [
                'application_name' => $application->name,
                'account_name' => $account->name,
                'setup_fee' => $application->setup_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'monthly_fee' => $application->monthly_fee,
                'monthly_minimum' => $application->monthly_minimum,
                'service_fee' => $application->service_fee,
                'confirmed_at' => $application->fees_confirmed_at?->format('Y-m-d H:i'),
            ], $application));

            // Log the email
            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'fees_confirmed',
                'recipient_email' => $application->user->email,
                'subject' => 'Application Fees Confirmed',
                'sent_at' => now(),
            ]);

            Log::info('Fees confirmed notification sent to user', [
                'application_id' => $application->id,
                'user_email' => $application->user->email,
            ]);
        }
    }
}