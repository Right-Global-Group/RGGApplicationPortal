<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeesChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Application $application;
    public Application $parentApplication;

    /**
     * Create a new event instance.
     */
    public function __construct(Application $application, Application $parentApplication)
    {
        $this->application = $application;
        $this->parentApplication = $parentApplication;
    }
}