<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeesConfirmedEvent
{
    use Dispatchable, SerializesModels;

    public Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;

        \Log::info('FeesConfirmedEvent instantiated', [
            'application_id' => $application->id,
            'account_id' => $application->account_id,
        ]);
    }
}