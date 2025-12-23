<?php

namespace App\Listeners;

use App\Events\ApplicationCreatedEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendApplicationCreatedEmail
{
    public function handle(ApplicationCreatedEvent $event): void
    {
        $application = $event->application;
        $account = $application->account;

        Log::info('SendApplicationCreatedEmail triggered', [
            'application_id' => $application->id,
            'account_id' => $account->id,
        ]);

        if (!$account || !$account->email) {
            Log::warning('Account or email missing in SendApplicationCreatedEmail', [
                'application' => $application,
            ]);
            return;
        }

        $statusUrl = route('applications.status', $application);
        $editUrl = route('applications.edit', $application) . '#documents';
        $loginUrl = route('account.login');

        // Send application created notification
        Mail::to($account->email)->send(new DynamicEmail('application_created', [
            'account_name' => $account->name,
            'application_name' => $application->name,
            'status_url' => $statusUrl,
            'edit_url' => $editUrl,
            'login_url' => $loginUrl,
            'created_at' => $application->created_at->format('d/m/Y H:i'),
        ]));

        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'application_created',
            'recipient_email' => $account->email,
            'subject' => 'New Application Created',
            'sent_at' => now(),
        ]);
    }
}