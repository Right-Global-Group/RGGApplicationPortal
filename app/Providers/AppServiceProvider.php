<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

use App\Schedules\ApplicationSchedule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/';

    public function register(): void
    {
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            (new ApplicationSchedule())->schedule($schedule);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));
        
        // Auth is handled by HandleInertiaRequests middleware
    }
}