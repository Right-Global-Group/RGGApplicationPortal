<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;
    // REMOVED SerializesModels to prevent model serialization

    public int $applicationId;
    public string $oldStatus;
    public string $newStatus;
    public int $progressPercentage;

    public function __construct(
        Application $application,
        string $oldStatus,
        string $newStatus
    ) {
        // Store only the data we need, not the entire model
        $this->applicationId = $application->id;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->progressPercentage = $application->status->progress_percentage;
    }

    public function broadcastOn()
    {
        return new Channel('applications.' . $this->applicationId);
    }

    public function broadcastWith()
    {
        return [
            'application_id' => $this->applicationId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'progress_percentage' => $this->progressPercentage,
        ];
    }
}