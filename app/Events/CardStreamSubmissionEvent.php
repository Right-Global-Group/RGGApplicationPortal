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

    public function __construct(Application $application, string $contractUrl)
    {
        $this->application = $application;
        $this->contractUrl = $contractUrl;

        \Log::info('CardStreamSubmissionEvent instantiated', [
            'application_id' => $application->id,
            'application_name' => $application->name,
            'contract_url' => $contractUrl,
        ]);
    }
}