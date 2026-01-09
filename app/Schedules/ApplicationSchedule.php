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
            ->runInBackground();

        // Dump expired application documents daily at 2 AM
        $schedule->command('applications:dump-expired-documents')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground();
    }
}