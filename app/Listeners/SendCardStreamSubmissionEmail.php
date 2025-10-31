<?php

namespace App\Listeners;

use App\Events\CardStreamSubmissionEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCardStreamSubmissionEmail
{
    public function handle(CardStreamSubmissionEvent $event): void
    {
        $application = $event->application;
        $contractUrl = $event->contractUrl;

        Log::info('SendCardStreamSubmissionEmail triggered', [
            'application_id' => $application->id,
            'application_name' => $application->name,
            'contract_url' => $contractUrl,
        ]);

        // CardStream email address
        $cardstreamEmail = 'contracts@cardstream.com'; // Update this to the correct email

        // Get the submitting user's details
        $submittedBy = auth()->guard('web')->check() 
            ? auth()->guard('web')->user()->name ?? auth()->guard('web')->user()->email
            : 'System';

        // Send submission email to CardStream
        Mail::to($cardstreamEmail)->send(new DynamicEmail('cardstream_submission', [
            'application_name' => $application->name,
            'account_name' => $application->account->name ?? 'N/A',
            'trading_name' => $application->trading_name ?? 'N/A',
            'submitted_by' => $submittedBy,
            'contract_url' => $contractUrl,
            'application_url' => route('applications.status', ['application' => $application->id]),
            'setup_fee' => $application->setup_fee,
            'transaction_percentage' => $application->transaction_percentage,
            'transaction_fixed_fee' => $application->transaction_fixed_fee,
            'monthly_fee' => $application->monthly_fee,
            'monthly_minimum' => $application->monthly_minimum,
            'service_fee' => $application->service_fee,
        ]));

        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'cardstream_submission',
            'recipient_email' => $cardstreamEmail,
            'subject' => 'New Application Submission - ' . $application->name,
            'sent_at' => now(),
        ]);

        Log::info('CardStream submission email sent successfully', [
            'application_id' => $application->id,
            'recipient' => $cardstreamEmail,
        ]);
    }
}