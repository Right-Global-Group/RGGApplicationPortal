<?php

namespace App\Listeners;

use App\Events\InvoiceReminderEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;

class SendInvoiceReminderEmail
{
    public function handle(InvoiceReminderEvent $event): void
    {
        $application = $event->application;
        $user = auth()->user();

        Mail::to($user->email)->send(new DynamicEmail('invoice_reminder', [
            'application_name' => $application->name,
            'account_name' => $application->account->name,
            'scaling_fee' => $application->scaling_fee,
            'application_url' => route('applications.status', $application),
        ]));

        EmailLog::create([
            'emailable_type' => get_class($application),
            'emailable_id' => $application->id,
            'email_type' => 'invoice_reminder',
            'recipient_email' => $user->email,
            'subject' => 'Reminder: Create Invoice in Xero',
            'sent_at' => now(),
        ]);
    }
}