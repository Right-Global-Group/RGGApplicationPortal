<?php

namespace App\Listeners;

use App\Events\FeesChangedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendFeesChangedEmail
{
    /**
     * Handle the event.
     */
    public function handle(FeesChangedEvent $event): void
    {
        $application = $event->application;
        $parentApplication = $event->parentApplication;
        $account = $application->account;

        Log::info('SendFeesChangedEmailListener triggered', [
            'application_id' => $application->id,
            'parent_application_id' => $parentApplication->id,
            'account_id' => $account->id,
            'account_email' => $account->email,
        ]);

        if (!$account || !$account->email) {
            Log::warning('Account or email missing in SendFeesChangedEmailListener', [
                'application_id' => $application->id,
            ]);
            return;
        }

        // Determine the correct URL based on authentication
        $statusUrl = $account->email 
            ? route('applications.status', $application) 
            : route('account.login');

        // Send fees changed notification to the account
        Mail::to($account->email)->send(new DynamicEmail('fees_changed', [
            'account_name' => $account->name,
            'application_name' => $application->name,
            'setup_fee' => $application->setup_fee,
            'transaction_percentage' => $application->transaction_percentage,
            'transaction_fixed_fee' => $application->transaction_fixed_fee,
            'monthly_fee' => $application->monthly_fee,
            'monthly_minimum' => $application->monthly_minimum,
            'service_fee' => $application->service_fee,
            'parent_application_name' => $parentApplication->name,
            'status_url' => $statusUrl,
        ], $application));

        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'fees_changed',
            'recipient_email' => $account->email,
            'subject' => 'Application Fees Changed - Confirmation Required',
            'sent_at' => now(),
        ]);

        Log::info('Fees changed notification sent to account', [
            'application_id' => $application->id,
            'account_email' => $account->email,
        ]);
    }
}