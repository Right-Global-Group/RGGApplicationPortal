<?php

namespace App\Listeners;

use App\Events\AccountCredentialsEvent;
use App\Mail\DynamicEmail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAccountCredentialsEmail
{
    public function handle(AccountCredentialsEvent $event): void
    {
        $account = $event->account;
        $plainPassword = $event->plainPassword;
    
        Log::info('SendAccountCredentialsEmail triggered', [
            'account_id' => $account->id,
            'account_email' => $account->email,
        ]);
    
        if (!$account || !$account->email) {
            Log::warning('Account or email missing in SendAccountCredentialsEmail', [
                'account' => $account,
            ]);
            return;
        }
    
        $loginUrl = route('account.login');
    
        // Send credentials email
        Mail::to($account->email)->send(new DynamicEmail('account_credentials', [
            'name' => $account->name,
            'email' => $account->email,
            'password' => $plainPassword,
            'login_url' => $loginUrl,
        ]));
    
        // Log the email
        EmailLog::create([
            'emailable_type' => get_class($account),
            'emailable_id' => $account->id,
            'email_type' => 'account_credentials',
            'recipient_email' => $account->email,
            'subject' => 'Your Account Credentials',
            'sent_at' => now(),
        ]);
    
        // Also log against all applications for this account
        foreach ($account->applications as $application) {
            EmailLog::create([
                'emailable_type' => \App\Models\Application::class,
                'emailable_id' => $application->id,
                'email_type' => 'account_credentials',
                'recipient_email' => $account->email,
                'subject' => 'Your Account Credentials',
                'sent_at' => now(),
            ]);
        }
    
        // Update credentials sent timestamp
        $account->update([
            'credentials_sent_at' => now(),
        ]);
    }
}