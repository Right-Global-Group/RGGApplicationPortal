<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectorSignedContractEvent
{
    use Dispatchable, SerializesModels;

    public Application $application;
    public string $signingUrl;

    /**
     * Create a new event instance.
     *
     * @param Application $application
     * @param string $signingUrl The merchant's signing URL
     */
    public function __construct(Application $application, string $signingUrl)
    {
        $this->application = $application;
        $this->signingUrl = $signingUrl;

        \Log::info('DirectorSignedContractEvent instantiated', [
            'application_id' => $application->id,
            'signing_url' => $signingUrl,
        ]);
    }
}