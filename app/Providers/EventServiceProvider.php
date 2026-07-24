<?php

namespace App\Providers;

use App\Events\AccountCredentialsEvent;
use App\Events\AccountLiveEvent;
use App\Events\AdditionalInfoRequestedEvent;
use App\Events\AllDocumentsUploadedEvent;
use App\Events\ApplicationApprovedEvent;
use App\Events\ApplicationCreatedEvent;
use App\Events\ApplicationMessagePosted;
use App\Events\CardStreamCredentialsEvent;
use App\Events\CardStreamCredentialsReminderEvent;
use App\Events\CardStreamSubmissionEvent;
use App\Events\CashflowsNoticeReadyForAccountEvent;
use App\Events\DirectorSignedContractEvent;
use App\Events\DocumentsDumpedEvent;
use App\Events\DocumentUploadReadyEvent;
use App\Events\DocuSignStatusChangeEvent;
use App\Events\FeesChangedEvent;
use App\Events\FeesConfirmationReminderEvent;
use App\Events\GatewayPartnerContractReadyEvent;
use App\Events\InvoiceReminderEvent;
use App\Events\MerchantContractReadyEvent;
use App\Events\WordPressCredentialsReminderEvent;
use App\Events\WordPressCredentialsRequestEvent;
use App\Listeners\SendAccountCredentialsEmail;
use App\Listeners\SendAccountLiveEmail;
use App\Listeners\SendAdditionalInfoRequestEmail;
use App\Listeners\SendAllDocumentsUploadedEmailListener;
use App\Listeners\SendApplicationApprovedEmail;
use App\Listeners\SendApplicationCreatedEmail;
use App\Listeners\SendApplicationMessagePostedEmail;
use App\Listeners\SendCardStreamCredentialsEmail;
use App\Listeners\SendCardStreamCredentialsReminderEmail;
use App\Listeners\SendCardStreamSubmissionEmail;
use App\Listeners\SendCashflowsNoticeReadyEmail;
use App\Listeners\SendDirectorSignedEmail;
use App\Listeners\SendDocumentsDumpedEmail;
use App\Listeners\SendDocumentUploadReadyEmail;
use App\Listeners\SendDocuSignStatusChangeEmail;
use App\Listeners\SendFeesChangedEmailListener;
use App\Listeners\SendFeesConfirmationReminderListener;
use App\Listeners\SendGatewayPartnerContractReadyEmail;
use App\Listeners\SendInvoiceReminderEmail;
use App\Listeners\SendMerchantContractReadyEmail;
use App\Listeners\SendWordPressCredentialsReminderEmail;
use App\Listeners\SendWordPressCredentialsRequestEmail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AccountCredentialsEvent::class => [
            SendAccountCredentialsEmail::class,
        ],
        ApplicationMessagePosted::class => [
            SendApplicationMessagePostedEmail::class,
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
        CashflowsNoticeReadyForAccountEvent::class => [
            SendCashflowsNoticeReadyEmail::class,
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
        DocumentsDumpedEvent::class => [
            SendDocumentsDumpedEmail::class,
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
