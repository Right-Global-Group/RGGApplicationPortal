<?php

namespace App\Listeners;

use App\Events\CashflowsNoticeReadyForAccountEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCashflowsNoticeReadyEmail
{
    public function handle(CashflowsNoticeReadyForAccountEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        if (!$account || !$account->email) {
            Log::warning('Cannot send Cashflows notice ready email - account or email missing', [
                'application_id' => $application->id,
            ]);
            return;
        }

        try {
            $redirect = route('applications.status', $application, absolute: false).'#section-actions';
            $signingUrl = MagicLinkService::for($account, $application, $redirect);

            $emailData = [
                'account_name' => $account->name,
                'application_name' => $application->name,
                'signing_url' => $signingUrl,
                'application_url' => $signingUrl,
            ];

            Mail::to($account->email)->send(
                new DynamicEmail('cashflows_notice_ready', $emailData)
            );

            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'cashflows_notice_ready',
                'recipient_email' => $account->email,
                'subject' => 'Please Sign Your Cashflows Switch Notice',
                'sent_at' => now(),
            ]);

            Log::info('Cashflows notice ready email sent', [
                'application_id' => $application->id,
                'account_email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Cashflows notice ready email', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
