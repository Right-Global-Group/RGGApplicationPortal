<?php

namespace App\Events;

use App\Models\ApplicationDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUploadedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ApplicationDocument $document;

    public function __construct(ApplicationDocument $document)
    {
        $this->document = $document;
    }
}