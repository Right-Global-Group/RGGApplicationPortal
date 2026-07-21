<?php

namespace App\Listeners;

use App\Events\ApplicationMessagePosted;
use App\Mail\DynamicEmail;
use App\Models\ApplicationMessage;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendApplicationMessagePostedEmail implements ShouldQueue
{
    public function handle(ApplicationMessagePosted $event): void
    {
        $message = $event->message;
        $application = $message->application;

        if (! $application) {
            Log::warning('Cannot send message notification - application missing', [
                'application_message_id' => $message->id,
            ]);

            return;
        }

        // Internal notes get their own email type so the template can scream
        // "do not forward this to the merchant".
        $emailType = $message->is_internal
            ? 'application_internal_note_posted'
            : 'application_message_posted';

        $emailData = [
            'sender_name' => $message->author['name'],
            'sender_is_staff' => $message->author['is_staff'],
            'application_name' => $application->name,
            'account_name' => $application->account?->name,
            'excerpt' => Str::limit($message->body, 300),
            'step_label' => $message->step_context['step_label'] ?? null,
            'thread_url' => url("/applications/{$application->id}/status#section-messages"),
        ];

        foreach ($this->recipients($message) as $recipient) {
            try {
                $mail = new DynamicEmail($emailType, [
                    ...$emailData,
                    'recipient_name' => $recipient['name'],
                ]);

                Mail::to($recipient['email'])->send($mail);

                EmailLog::create([
                    'emailable_type' => get_class($application),
                    'emailable_id' => $application->id,
                    'email_type' => $emailType,
                    'recipient_email' => $recipient['email'],
                    'subject' => $mail->build()->subject,
                    'sent_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send application message notification', [
                    'application_message_id' => $message->id,
                    'recipient_email' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Who gets told about this message — never the author.
     *
     * Staff message (visible)      → the application's merchant account.
     * Merchant message             → all staff users.
     * Internal note (staff-only)   → all staff users except the author.
     *
     * @return array<int, array{email: string, name: string}>
     */
    private function recipients(ApplicationMessage $message): array
    {
        if ($message->isFromStaff() && ! $message->is_internal) {
            $account = $message->application->account;

            if (! $account?->email) {
                Log::warning('Cannot notify merchant of new message - account email missing', [
                    'application_message_id' => $message->id,
                ]);

                return [];
            }

            return [['email' => $account->email, 'name' => $account->name]];
        }

        // Merchant messages and internal notes both fan out to staff,
        // excluding the author (only relevant for internal notes).
        return User::query()
            ->whereNotNull('email')
            ->when($message->user_id, fn ($query) => $query->whereKeyNot($message->user_id))
            ->get()
            ->map(fn (User $user) => [
                'email' => $user->email,
                'name' => trim("{$user->first_name} {$user->last_name}"),
            ])
            ->all();
    }
}
