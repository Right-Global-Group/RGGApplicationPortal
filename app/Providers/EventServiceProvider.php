<?php

namespace App\Providers;

use App\Events\AccountCredentialsEvent;
use App\Events\ApplicationCreatedEvent;
use App\Events\ApplicationStatusChanged;
use App\Events\FeesChangedEvent;
use App\Events\FeesConfirmedEvent;
use App\Events\FeesConfirmationReminderEvent;
use App\Listeners\SendAccountCredentialsEmail;
use App\Listeners\SendApplicationCreatedEmail;
use App\Events\ApplicationApprovedEvent;
use App\Listeners\SendApplicationApprovedEmail;
use App\Listeners\SendFeesChangedEmailListener;
use App\Listeners\SendAdditionalInfoRequestEmail;
use App\Listeners\SendFeesConfirmationReminderListener;
use App\Events\AdditionalInfoRequestedEvent;
use App\Listeners\SendFeesConfirmedEmail;
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
        FeesConfirmedEvent::class => [
            SendFeesConfirmedEmail::class,
        ],
        FeesChangedEvent::class => [
            SendFeesChangedEmailListener::class,
        ],
        FeesConfirmationReminderEvent::class => [  // ADD THIS
            SendFeesConfirmationReminderListener::class,
        ],
        DocumentUploadedEvent::class => [
            SendDocumentUploadedEmailListener::class,
        ],
        AllDocumentsUploadedEvent::class => [
            SendAllDocumentsUploadedEmailListener::class,
        ],
        ApplicationApprovedEvent::class => [
            SendApplicationApprovedEmail::class,
        ],
        AdditionalInfoRequestedEvent::class => [  // FIX THIS - was backwards
            SendAdditionalInfoRequestEmail::class,
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