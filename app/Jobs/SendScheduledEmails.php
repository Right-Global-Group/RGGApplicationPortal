<?php

namespace App\Jobs;

use App\Events\AccountCredentialsEvent;
use App\Events\AdditionalInfoRequestedEvent;
use App\Events\ApplicationCreatedEvent;
use App\Events\FeesConfirmationReminderEvent;
use App\Events\WordPressCredentialsReminderEvent;
use App\Events\CardStreamCredentialsReminderEvent;
use App\Models\EmailReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('SendScheduledEmails job started');

        $dueReminders = EmailReminder::where('is_active', true)
            ->where('next_send_at', '<=', now())
            ->get();

        Log::info('Found due reminders', ['count' => $dueReminders->count()]);

        foreach ($dueReminders as $reminder) {
            try {
                $this->sendReminderEmail($reminder);
                $reminder->updateNextSendDate();
                
                Log::info('Reminder processed successfully', [
                    'reminder_id' => $reminder->id,
                    'email_type' => $reminder->email_type,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send reminder email', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SendScheduledEmails job completed');
    }

    private function sendReminderEmail(EmailReminder $reminder): void
    {
        $remindable = $reminder->remindable;

        if (!$remindable) {
            Log::warning('Remindable entity not found', ['reminder_id' => $reminder->id]);
            return;
        }

        switch ($reminder->email_type) {
            case 'account_credentials':
                if ($remindable instanceof \App\Models\Account) {
                    $plainPassword = \App\Models\Account::generatePassword();
                    $remindable->update(['password' => $plainPassword]);
                    event(new AccountCredentialsEvent($remindable, $plainPassword));
                }
                break;

            case 'application_created':
                if ($remindable instanceof \App\Models\Application) {
                    event(new ApplicationCreatedEvent($remindable));
                }
                break;

            case 'additional_info_requested':
                if ($remindable instanceof \App\Models\Application) {
                    $notes = $remindable->status?->additional_info_notes ?? 'Additional information is required.';
                    event(new AdditionalInfoRequestedEvent($remindable, $notes));
                }
                break;

            case 'wordpress_credentials_request':
                if ($remindable instanceof \App\Models\Application) {
                    event(new WordPressCredentialsReminderEvent($remindable));
                }
                break;

            case 'cardstream_credentials':
                if ($remindable instanceof \App\Models\Application) {
                    event(new CardStreamCredentialsReminderEvent($remindable));
                }
                break;

            case 'account_message_to_user':
                if ($remindable instanceof \App\Models\Application) {
                    $message = $remindable->status?->account_message_notes ?? 'Follow-up message from account.';
                    event(new \App\Events\AccountMessageToUserEvent($remindable, $message));
                }
                break;

            default:
                Log::warning('Unknown email type for reminder', [
                    'reminder_id' => $reminder->id,
                    'email_type' => $reminder->email_type,
                ]);
        }
    }
}