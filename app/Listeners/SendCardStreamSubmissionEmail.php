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
        $documents = $event->documents;

        Log::info('SendCardStreamSubmissionEmail triggered', [
            'application_id' => $application->id,
            'application_name' => $application->name,
            'contract_url' => $contractUrl,
            'document_count' => count($documents),
        ]);

        // CardStream email address
        $cardstreamEmail = 'rachel.attwood@g2pay.co.uk';

        // Get the submitting user's details
        $submittedBy = auth()->guard('web')->check() 
            ? auth()->guard('web')->user()->name ?? auth()->guard('web')->user()->email
            : 'System';

        // Prepare email data
        $emailData = [
            'application_name' => $application->name,
            'account_name' => $application->account->name ?? 'N/A',
            'trading_name' => $application->trading_name ?? 'N/A',
            'submitted_by' => $submittedBy,
            'contract_url' => $contractUrl,
            'application_url' => route('applications.status', ['application' => $application->id]),
            'scaling_fee' => $application->scaling_fee,
            'transaction_percentage' => $application->transaction_percentage,
            'transaction_fixed_fee' => $application->transaction_fixed_fee,
            'monthly_fee' => $application->monthly_fee,
            'monthly_minimum' => $application->monthly_minimum,
            'service_fee' => $application->service_fee,
            'document_count' => count($documents),
        ];

        // Send email with attachments
        Mail::to($cardstreamEmail)->send(
            new DynamicEmail('cardstream_submission', $emailData, $documents)
        );

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
            'attachments' => count($documents),
        ]);
    }
}