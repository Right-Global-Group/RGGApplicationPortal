<?php

namespace App\Providers;

use App\Events\AccountCredentialsEvent;
use App\Events\ApplicationCreatedEvent;
use App\Events\ApplicationStatusChanged;
use App\Events\FeesChangedEvent;
use App\Events\FeesConfirmationReminderEvent;
use App\Events\AdditionalInfoRequestedEvent;
use App\Events\ApplicationApprovedEvent;
use App\Events\AllDocumentsUploadedEvent;
use App\Events\DocuSignStatusChangeEvent;
use App\Events\MerchantContractReadyEvent;
use App\Events\DirectorSignedContractEvent;
use App\Events\GatewayPartnerContractReadyEvent;
use App\Events\WordPressCredentialsRequestEvent;
use App\Events\WordPressCredentialsReminderEvent;
use App\Events\CardStreamSubmissionEvent;
use App\Events\InvoiceReminderEvent;
use App\Events\CardStreamCredentialsEvent;
use App\Events\CardStreamCredentialsReminderEvent;
use App\Events\AccountLiveEvent;
use App\Events\DocumentUploadReadyEvent;
use App\Events\AccountMessageToUserEvent;

use App\Listeners\SendAccountCredentialsEmail;
use App\Listeners\SendApplicationCreatedEmail;
use App\Listeners\SendFeesChangedEmailListener;
use App\Listeners\SendFeesConfirmationReminderListener;
use App\Listeners\SendAdditionalInfoRequestEmail;
use App\Listeners\SendApplicationApprovedEmail;
use App\Listeners\SendAllDocumentsUploadedEmailListener;
use App\Listeners\SendDocuSignStatusChangeEmail;
use App\Listeners\SendMerchantContractReadyEmail;
use App\Listeners\SendDirectorSignedEmail;
use App\Listeners\SendGatewayPartnerContractReadyEmail;
use App\Listeners\SendWordPressCredentialsRequestEmail;
use App\Listeners\SendWordPressCredentialsReminderEmail;
use App\Listeners\SendCardStreamSubmissionEmail;
use App\Listeners\SendInvoiceReminderEmail;
use App\Listeners\SendCardStreamCredentialsEmail;
use App\Listeners\SendCardStreamCredentialsReminderEmail;
use App\Listeners\SendAccountLiveEmail;
use App\Listeners\SendDocumentUploadReadyEmail;
use App\Listeners\SendAccountMessageToUserEmail;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
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
        MerchantContractReadyEvent::class => [
            SendMerchantContractReadyEmail::class,
        ],
        DirectorSignedContractEvent::class => [
            SendDirectorSignedEmail::class,
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
        InvoiceReminderEvent::class => [
            SendInvoiceReminderEmail::class,
        ],
        CardStreamCredentialsEvent::class => [
            SendCardStreamCredentialsEmail::class,
        ],
        CardStreamCredentialsReminderEvent::class => [
            SendCardStreamCredentialsReminderEmail::class,
        ],
        DocumentUploadReadyEvent::class => [
            SendDocumentUploadReadyEmail::class,
        ],
        AccountLiveEvent::class => [
            SendAccountLiveEmail::class,
        ],
        AccountMessageToUserEvent::class => [
            SendAccountMessageToUserEmail::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}