<?php

namespace App\Listeners;

use App\Events\ApplicationApprovedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendApplicationApprovedEmail
{
    public function handle(ApplicationApprovedEvent $event): void
    {
        $account = $event->account;
        $application = $event->application;

        if (!$account || !$account->email) {
            Log::warning('Account or email missing in SendApplicationApprovedEmail', ['account' => $account]);
            return;
        }

        $appUrl = route('account.login'); // default fallback
        if ($application && $application->id) {
            $appUrl = route('applications.status', ['application' => $application->id]);
        }

        Mail::to($account->email)->send(new DynamicEmail('application_approved', [
            'name' => $account->name,
            'application_name' => $application->name,
            'application_url' => $appUrl,
        ]));

        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'application_approved',
            'recipient_email' => $account->email,
            'subject' => 'Your Application Has Been Approved',
            'sent_at' => now(),
        ]);
    }
}
