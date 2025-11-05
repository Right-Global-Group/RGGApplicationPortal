<?php

namespace App\Listeners;

use App\Events\AccountLiveEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;

class SendAccountLiveEmail
{
    public function handle(AccountLiveEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        Mail::to($account->email)->send(new DynamicEmail('account_live', [
            'account_name' => $account->name,
            'application_name' => $application->name,
            'application_url' => route('applications.status', $application),
            'wordpress_url' => $application->wordpress_url,
        ]));

        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'account_live',
            'recipient_email' => $account->email,
            'subject' => 'Congratulations! Your Account is Live',
            'sent_at' => now(),
        ]);
    }
}