<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationCreatedEvent
{
    use Dispatchable, SerializesModels;

    public Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;

        \Log::info('ApplicationCreatedEvent instantiated', [
            'application_id' => $application->id,
            'account_id' => $application->account_id,
        ]);
    }
}