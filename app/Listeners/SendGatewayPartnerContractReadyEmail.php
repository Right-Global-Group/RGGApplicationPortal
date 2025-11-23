<?php

namespace App\Listeners;

use App\Events\GatewayPartnerContractReadyEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendGatewayPartnerContractReadyEmail
{
    public function handle(GatewayPartnerContractReadyEvent $event): void
    {
        $application = $event->application;
        
        if (!$application->gateway_partner) {
            Log::warning('Cannot send gateway contract email - gateway partner not set', [
                'application_id' => $application->id,
            ]);
            return;
        }

        $partnerEmail = $application->gateway_partner_email;
        
        if (!$partnerEmail) {
            Log::warning('Cannot send gateway contract email - partner email missing', [
                'application_id' => $application->id,
                'gateway_partner' => $application->gateway_partner,
            ]);
            return;
        }

        try {
            $emailData = [
                'application_name' => $application->name,
                'trading_name' => $application->trading_name,
                'gateway_partner_name' => $application->gateway_partner_name,
                'signing_url' => $event->signingUrl,
                'scaling_fee' => $application->scaling_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'monthly_fee' => $application->monthly_fee,
            ];

            Mail::to($partnerEmail)->send(
                new DynamicEmail('gateway_partner_contract_ready', $emailData)
            );

            // Log the email
            EmailLog::create([
                'emailable_type' => get_class($application),
                'emailable_id' => $application->id,
                'email_type' => 'gateway_partner_contract_ready',
                'recipient_email' => $partnerEmail,
                'subject' => "New Merchant Application Contract - {$application->name}",
                'sent_at' => now(),
            ]);

            Log::info('Gateway partner contract ready email sent', [
                'application_id' => $application->id,
                'gateway_partner' => $application->gateway_partner,
                'partner_email' => $partnerEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send gateway partner contract ready email', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}