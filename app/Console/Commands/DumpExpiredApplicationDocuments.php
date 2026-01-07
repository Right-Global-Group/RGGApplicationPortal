<?php

namespace App\Console\Commands;

use App\Events\DocumentsDumpedEvent;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DumpExpiredApplicationDocuments extends Command
{
    protected $signature = 'applications:dump-expired-documents';
    protected $description = 'Dump documents for applications approved over 1 month ago';

    public function handle()
    {
        $this->info('Starting to dump expired application documents...');

        // Find applications that were approved over 1 month ago
        $applications = Application::whereHas('status', function ($query) {
            $query->whereNotNull('application_approved_at')
                  ->where('application_approved_at', '<=', now()->subMonth());
        })->get();

        $this->info("Found {$applications->count()} applications to process");

        $totalDumped = 0;

        foreach ($applications as $application) {
            $dumpedCount = $this->dumpApplicationDocuments($application);
            $totalDumped += $dumpedCount;

            if ($dumpedCount > 0) {
                $this->info("Dumped {$dumpedCount} documents for Application #{$application->id}");
            }
        }

        $this->info("Total documents dumped: {$totalDumped}");
        
        return 0;
    }

    private function dumpApplicationDocuments(Application $application): int
    {
        // Get all documents that haven't been dumped yet, excluding application_form and contract categories
        $documents = ApplicationDocument::where('application_id', $application->id)
            ->whereNull('dumped_at')
            ->whereNotIn('document_category', ['application_form', 'contract'])
            ->get();

        if ($documents->isEmpty()) {
            return 0;
        }

        $dumpedDocuments = [];

        foreach ($documents as $document) {
            // Delete the actual file from storage
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // Update document record to mark as dumped
            $document->update([
                'dumped_at' => now(),
                'dumped_reason' => 'Automatic deletion 1 month after application approval',
            ]);

            $dumpedDocuments[] = [
                'category' => $document->document_category,
                'filename' => $document->original_filename,
                'uploaded_at' => $document->created_at->format('Y-m-d H:i'),
            ];

            \Log::info('Document dumped', [
                'document_id' => $document->id,
                'application_id' => $application->id,
                'file_path' => $document->file_path,
            ]);
        }

        // Fire event to send email notification
        if (!empty($dumpedDocuments)) {
            event(new DocumentsDumpedEvent($application, $dumpedDocuments));
        }

        return count($dumpedDocuments);
    }
}