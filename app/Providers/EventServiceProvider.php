<?php

namespace App\Providers;

use App\Events\AccountCredentialsEvent;
use App\Events\ApplicationCreatedEvent;
use App\Events\ApplicationStatusChanged;
use App\Listeners\SendAccountCredentialsEmail;
use App\Listeners\SendApplicationCreatedEmail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        AccountCredentialsEvent::class => [
            SendAccountCredentialsEmail::class,
        ],
        ApplicationCreatedEvent::class => [
            SendApplicationCreatedEmail::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}