<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\DocuSignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillDocuSignDocuments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'docusign:backfill-documents 
                            {--application_id= : Specific application ID to backfill}
                            {--dry-run : Run without actually downloading/storing}';

    /**
     * The console command description.
     */
    protected $description = 'Backfill missing DocuSign signed documents for applications with completed contracts';

    private DocuSignService $docuSignService;

    public function __construct(DocuSignService $docuSignService)
    {
        parent::__construct();
        $this->docuSignService = $docuSignService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificAppId = $this->option('application_id');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No files will be downloaded or stored');
        }

        $this->info('🔍 Finding applications with signed contracts but missing documents...');

        // Find applications with DocuSign envelope IDs
        $query = Application::whereHas('status', function ($q) {
            $q->whereNotNull('docusign_envelope_id')
              ->whereNotNull('contract_signed_at'); // Only fully signed contracts
        });

        if ($specificAppId) {
            $query->where('id', $specificAppId);
            $this->info("Filtering to application ID: {$specificAppId}");
        }

        $applications = $query->get();

        $this->info("Found {$applications->count()} application(s) with signed contracts");

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($applications as $application) {
            $envelopeId = $application->status->docusign_envelope_id;
            
            $this->line('');
            $this->info("Processing: {$application->name} (ID: {$application->id})");
            $this->line("   Envelope ID: {$envelopeId}");

            // Check what documents are missing
            $hasContract = ApplicationDocument::where('application_id', $application->id)
                ->where('document_category', 'contract')
                ->whereNotNull('file_path')
                ->exists();

            $hasApplicationForm = ApplicationDocument::where('application_id', $application->id)
                ->where('document_category', 'application_form')
                ->whereNotNull('file_path')
                ->exists();

            if ($hasContract && $hasApplicationForm) {
                $this->line('All documents already present - skipping');
                $skippedCount++;
                continue;
            }

            $missing = [];
            if (!$hasContract) $missing[] = 'contract';
            if (!$hasApplicationForm) $missing[] = 'application_form';

            $this->warn('   ⚠️  Missing: ' . implode(', ', $missing));

            if ($dryRun) {
                $this->line('   [DRY RUN] Would download and store missing documents');
                $processedCount++;
                continue;
            }

            // Download and store missing documents
            try {
                if (!$hasApplicationForm) {
                    $this->line('Downloading application form (document 2)...');
                    $this->downloadAndStoreDocument(
                        $application,
                        $envelopeId,
                        '1',
                        'application_form',
                        "Application_Form_{$application->name}.pdf"
                    );
                    $this->line('Application form stored');
                }
                if (!$hasContract) {
                    $this->line('Downloading contract (document 1)...');
                    $this->downloadAndStoreDocument(
                        $application,
                        $envelopeId,
                        '2',
                        'contract',
                        "Signed_Contract_{$application->name}.pdf"
                    );
                    $this->line('Contract stored');
                }

                $processedCount++;
                $this->info('Successfully backfilled documents');

            } catch (\Exception $e) {
                $errorCount++;
                $this->error('Error: ' . $e->getMessage());
                
                // Log the full error for debugging
                \Log::error('Backfill DocuSign documents failed', [
                    'application_id' => $application->id,
                    'envelope_id' => $envelopeId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Summary
        $this->line('');
        $this->info('📊 Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Processed', $processedCount],
                ['Skipped (already complete)', $skippedCount],
                ['Errors', $errorCount],
                ['Total', $applications->count()],
            ]
        );

        if ($dryRun) {
            $this->warn('');
            $this->warn('🔍 This was a DRY RUN - no changes were made');
            $this->line('Remove --dry-run flag to actually download and store documents');
        }

        return Command::SUCCESS;
    }

    /**
     * Download and store a single document from DocuSign
     */
    private function downloadAndStoreDocument(
        Application $application,
        string $envelopeId,
        string $documentId,
        string $documentCategory,
        string $filename
    ): void {
        // Download the document from DocuSign
        $base64Content = $this->docuSignService->downloadEnvelopeDocument($envelopeId, $documentId);
        $pdfContent = base64_decode($base64Content);

        // Store the file
        $directory = "applications/{$application->id}/documents";
        $storagePath = "{$directory}/" . time() . "_{$filename}";

        Storage::disk('public')->put($storagePath, $pdfContent);

        \Log::info('Backfilled document', [
            'application_id' => $application->id,
            'envelope_id' => $envelopeId,
            'document_id' => $documentId,
            'category' => $documentCategory,
            'storage_path' => $storagePath,
            'file_size' => strlen($pdfContent),
        ]);

        // Create or update the document record
        ApplicationDocument::updateOrCreate(
            [
                'application_id' => $application->id,
                'document_category' => $documentCategory,
                'external_id' => $envelopeId,
                'external_system' => 'docusign',
            ],
            [
                'document_type' => 'application/pdf',
                'file_path' => $storagePath,
                'original_filename' => $filename,
                'uploaded_by' => null,
                'uploaded_by_type' => null,
                'status' => 'completed',
                'completed_at' => $application->status->contract_signed_at ?? now(),
            ]
        );
    }
}