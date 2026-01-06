<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentsDumpedEvent
{
    use Dispatchable, SerializesModels;

    public Application $application;
    public array $dumpedDocuments;

    public function __construct(Application $application, array $dumpedDocuments)
    {
        $this->application = $application;
        $this->dumpedDocuments = $dumpedDocuments;

        \Log::info('DocumentsDumpedEvent instantiated', [
            'application_id' => $application->id,
            'document_count' => count($dumpedDocuments),
        ]);
    }
}