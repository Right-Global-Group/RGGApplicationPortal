<?php

namespace App\Listeners;

use App\Events\DirectorSignedContractEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendDirectorSignedEmail
{
    public function handle(DirectorSignedContractEvent $event): void
    {
        $application = $event->application;
        $signingUrl = $event->signingUrl;

        Log::info('SendDirectorSignedEmail triggered', [
            'application_id' => $application->id,
            'merchant_email' => $application->account->email,
        ]);

        $emailData = [
            'account_name' => $application->account->name,
            'application_name' => $application->name,
            'signing_url' => $signingUrl,
            'application_url' => route('applications.status', ['application' => $application->id]),
        ];

        // Send email to merchant
        Mail::to($application->account->email)->send(
            new DynamicEmail('director_signed_contract', $emailData)
        );

        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'director_signed_contract',
            'recipient_email' => $application->account->email,
            'subject' => 'Ready for Your Signature - Contract Approved',
            'sent_at' => now(),
        ]);

        Log::info('Director signed notification email sent to merchant', [
            'application_id' => $application->id,
            'recipient' => $application->account->email,
        ]);
    }
}