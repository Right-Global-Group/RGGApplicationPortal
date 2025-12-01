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
        $payoutOption = $event->payoutOption; // Get from event

        Log::info('SendCardStreamSubmissionEmail triggered', [
            'application_id' => $application->id,
            'application_name' => $application->name,
            'contract_url' => $contractUrl,
            'document_count' => count($documents),
            'payout_option' => $payoutOption,
        ]);

        // CardStream email address
        $cardstreamEmail = 'max.behrens@rightglobalgroup.com';

        // Get the submitting user's details
        $submittedBy = auth()->guard('web')->check() 
            ? auth()->guard('web')->user()->name ?? auth()->guard('web')->user()->email
            : 'System';

        // Format payout option for display
        $payoutTiming = match($payoutOption) {
            'daily' => 'Daily (T+1)',
            'every_3_days' => 'Every 3 Days (T+3)',
            default => 'Daily (T+1)',
        };

        // Prepare email data
        $emailData = [
            'application_name' => $application->name,
            'account_name' => $application->account->name ?? 'N/A',
            'account_email' => $application->account->email ?? 'N/A',
            'account_mobile' => $application->account->mobile ?? 'N/A',
            'trading_name' => $application->trading_name ?? 'N/A',
            'submitted_by' => $submittedBy,
            'contract_url' => $contractUrl,
            'application_url' => route('applications.status', ['application' => $application->id]),
        
            // Fee
            'transaction_percentage' => $application->transaction_percentage ?? 0,
        
            // Payout option - use the formatted version from event
            'payout_option' => $payoutOption,
            'payout_timing' => $payoutTiming, // This is what the email template uses
        
            // Attachments
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
            'payout_timing' => $payoutTiming,
        ]);
    }
}