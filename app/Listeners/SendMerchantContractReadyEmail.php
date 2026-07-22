<?php

namespace App\Listeners;

use App\Events\MerchantContractReadyEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendMerchantContractReadyEmail
{
    public function handle(MerchantContractReadyEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        if (!$account || !$account->email) {
            Log::warning('Cannot send merchant contract email - account or email missing', [
                'application_id' => $application->id,
            ]);
            return;
        }

        try {
            // Signs the merchant in on the way, so the button they need is exactly
            // where the click lands - no login wall between the email and the task.
            $redirect = route('applications.status', $application, absolute: false).'#section-actions';
            $signingUrl = MagicLinkService::for($account, $application, $redirect);

            $emailData = [
                'account_name' => $account->name,
                'application_name' => $application->name,
                'signing_url' => $signingUrl,
                'application_url' => $signingUrl,
            ];

            Mail::to($account->email)->send(
                new DynamicEmail('merchant_contract_ready', $emailData)
            );

            // Log the email
            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'merchant_contract_ready',
                'recipient_email' => $account->email,
                'subject' => 'Please Sign Your Merchant Application Contract',
                'sent_at' => now(),
            ]);

            Log::info('Merchant contract ready email sent', [
                'application_id' => $application->id,
                'account_email' => $account->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send merchant contract ready email', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}