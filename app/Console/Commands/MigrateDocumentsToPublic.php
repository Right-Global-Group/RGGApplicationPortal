<?php

namespace App\Console\Commands;

use App\Models\ApplicationDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateDocumentsToPublic extends Command
{
    protected $signature = 'documents:migrate-to-public';
    protected $description = 'Move documents from private to public storage';

    public function handle()
    {
        $documents = ApplicationDocument::all();
        $moved = 0;
        $failed = 0;
        $notFound = 0;
        $skipped = 0;

        $this->info("Found {$documents->count()} documents to migrate...\n");

        foreach ($documents as $document) {
            // Skip documents with null or empty file_path
            if (empty($document->file_path)) {
                $this->warn("Document ID {$document->id}: No file path - SKIPPING");
                $skipped++;
                continue;
            }

            $this->info("Processing: {$document->file_path}");
            
            if (Storage::disk('private')->exists($document->file_path)) {
                try {
                    // Copy file to public disk
                    $content = Storage::disk('private')->get($document->file_path);
                    Storage::disk('public')->put($document->file_path, $content);
                    
                    // Verify the file was copied successfully
                    if (Storage::disk('public')->exists($document->file_path)) {
                        // Delete from private disk
                        Storage::disk('private')->delete($document->file_path);
                        
                        $moved++;
                        $this->line("  ✓ Moved successfully");
                    } else {
                        $failed++;
                        $this->error("  ✗ Failed to verify copy");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("  ✗ Failed: {$e->getMessage()}");
                }
            } elseif (Storage::disk('public')->exists($document->file_path)) {
                $this->line("  → Already in public storage");
                $moved++;
            } else {
                $notFound++;
                $this->warn("  ! File not found in either location: {$document->file_path}");
                $this->warn("    Document ID: {$document->id}, Category: {$document->document_category}");
            }
        }

        $this->newLine();
        $this->info("Migration complete!");
        $this->info("✓ Moved/Already present: {$moved} files");
        
        if ($skipped > 0) {
            $this->warn("⊘ Skipped (no file path): {$skipped} records");
        }
        
        if ($notFound > 0) {
            $this->warn("! Not found: {$notFound} files");
        }
        
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed} files");
        }

        // Optionally clean up empty records
        if ($skipped > 0 || $notFound > 0) {
            $this->newLine();
            if ($this->confirm('Do you want to delete document records with missing files?', false)) {

                // Delete null or empty file_path
                $deleted = ApplicationDocument::whereNull('file_path')
                    ->orWhere('file_path', '')
                    ->delete();
            
                $this->info("Deleted {$deleted} records with null or empty file_path");
            
                // Delete documents where file_path is set but file is missing from storage
                $deletedMissing = 0;
            
                foreach (ApplicationDocument::whereNotNull('file_path')->where('file_path', '!=', '')->get() as $doc) {
                    if (
                        !Storage::disk('public')->exists($doc->file_path) &&
                        !Storage::disk('private')->exists($doc->file_path)
                    ) {
                        $doc->delete();
                        $deletedMissing++;
                    }
                }
            
                if ($deletedMissing > 0) {
                    $this->info("Deleted {$deletedMissing} records whose files do not exist in storage");
                }
            }            
        }

        return 0;
    }
}