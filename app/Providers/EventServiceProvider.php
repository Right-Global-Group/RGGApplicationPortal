<?php

namespace App\Providers;

use App\Events\AccountCredentialsEvent;
use App\Events\ApplicationCreatedEvent;
use App\Events\ApplicationStatusChanged;
use App\Events\FeesChangedEvent;
use App\Events\FeesConfirmationReminderEvent;
use App\Events\AdditionalInfoRequestedEvent;
use App\Events\ApplicationApprovedEvent;
use App\Events\DocumentUploadedEvent;
use App\Events\AllDocumentsUploadedEvent;
use App\Events\DocuSignStatusChangeEvent;
// Gateway & WordPress events
use App\Events\MerchantContractReadyEvent;
use App\Events\GatewayPartnerContractReadyEvent;
use App\Events\WordPressCredentialsRequestEvent;
use App\Events\WordPressCredentialsReminderEvent;
use App\Events\CardStreamSubmissionEvent;

use App\Listeners\SendAccountCredentialsEmail;
use App\Listeners\SendApplicationCreatedEmail;
use App\Listeners\SendFeesChangedEmailListener;
use App\Listeners\SendFeesConfirmationReminderListener;
use App\Listeners\SendAdditionalInfoRequestEmail;
use App\Listeners\SendApplicationApprovedEmail;
use App\Listeners\SendDocumentUploadedEmailListener;
use App\Listeners\SendAllDocumentsUploadedEmailListener;
use App\Listeners\SendDocuSignStatusChangeEmail;
// Gateway & WordPress listeners
use App\Listeners\SendMerchantContractReadyEmail;
use App\Listeners\SendGatewayPartnerContractReadyEmail;
use App\Listeners\SendWordPressCredentialsRequestEmail;
use App\Listeners\SendWordPressCredentialsReminderEmail;
use App\Listeners\SendCardStreamSubmissionEmail;

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
        FeesChangedEvent::class => [
            SendFeesChangedEmailListener::class,
        ],
        FeesConfirmationReminderEvent::class => [
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
        AdditionalInfoRequestedEvent::class => [
            SendAdditionalInfoRequestEmail::class,
        ],
        DocuSignStatusChangeEvent::class => [
            SendDocuSignStatusChangeEmail::class,
        ],
        // Gateway & WordPress event mappings
        MerchantContractReadyEvent::class => [
            SendMerchantContractReadyEmail::class,
        ],
        GatewayPartnerContractReadyEvent::class => [
            SendGatewayPartnerContractReadyEmail::class,
        ],
        WordPressCredentialsRequestEvent::class => [
            SendWordPressCredentialsRequestEmail::class,
        ],
        WordPressCredentialsReminderEvent::class => [
            SendWordPressCredentialsReminderEmail::class,
        ],
        CardStreamSubmissionEvent::class => [
            SendCardStreamSubmissionEmail::class,
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