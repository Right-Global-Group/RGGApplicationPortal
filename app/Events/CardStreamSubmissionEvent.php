<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardStreamSubmissionEvent
{
    use Dispatchable, SerializesModels;

    public Application $application;
    public string $contractUrl;
    public array $documents;
    public string $payoutOption;

    /**
     * Create a new event instance.
     *
     * @param Application $application
     * @param string $contractUrl DocuSign contract URL
     * @param array $documents Array of document data with paths for attachments
     * @param string $payoutOption The selected payout timing (daily or every_3_days)
     */
    public function __construct(Application $application, string $contractUrl, array $documents = [], string $payoutOption = 'daily')
    {
        $this->application = $application;
        $this->contractUrl = $contractUrl;
        $this->documents = $documents;
        $this->payoutOption = $payoutOption;

        \Log::info('CardStreamSubmissionEvent instantiated', [
            'application_id' => $application->id,
            'application_name' => $application->name,
            'contract_url' => $contractUrl,
            'document_count' => count($documents),
            'payout_option' => $payoutOption,
        ]);
    }
}