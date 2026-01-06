<?php

namespace App\Schedules;

use Illuminate\Console\Scheduling\Schedule;
use App\Jobs\SendScheduledEmails;

class ApplicationSchedule
{
    public function schedule(Schedule $schedule): void
    {
        // Send scheduled reminder emails every 5 minutes
        $schedule->job(new SendScheduledEmails)
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->runInBackground()
            ->onSuccess(function () {
                \Log::info('SendScheduledEmails job completed successfully');
            })
            ->onFailure(function () {
                \Log::error('SendScheduledEmails job failed');
            });

        // Dump expired application documents daily at 2 AM
        $schedule->command('applications:dump-expired-documents')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                \Log::info('DumpExpiredApplicationDocuments completed successfully');
            })
            ->onFailure(function () {
                \Log::error('DumpExpiredApplicationDocuments failed');
            });
    }
}