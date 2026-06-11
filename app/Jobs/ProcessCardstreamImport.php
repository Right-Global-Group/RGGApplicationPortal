<?php

namespace App\Jobs;

use App\Models\CardstreamImport;
use App\Models\CardstreamTransactionSummary;
use App\Models\CardstreamTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessCardstreamImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $timeout = 600;
    public $tries = 1;

    public function __construct(
        public int $importId,
        public string $filePath
    ) {}

    public function handle(): void
    {
        // Version marker: if you don't see this line in the log on the next run,
        // the worker is running OLD code -> run `php artisan queue:restart`.
        \Log::info('ProcessCardstreamImport v2 (diagnostic) starting', [
            'import_id' => $this->importId,
            'file_path' => $this->filePath,
            'file_exists' => file_exists($this->filePath),
            'file_size' => file_exists($this->filePath) ? filesize($this->filePath) : null,
        ]);

        $import = CardstreamImport::find($this->importId);

        if (!$import) {
            \Log::error('Import not found', ['import_id' => $this->importId]);
            return;
        }

        $import->status = 'processing';
        $import->processed_rows = 0;
        $import->save();

        \Log::info('Status updated, current status:', ['status' => $import->fresh()->status]);

        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $processedCount = 0;
            $chunkSize = 500; // Reduced from 1000 to 500 for better memory management

            \Log::info('Spreadsheet loaded', [
                'highest_row' => $highestRow,
                'highest_column' => $highestColumn,
            ]);

            // Detect CSV format from header row.
            // BOM-safe: strip a leading UTF-8 BOM so the first header (e.g. "ContactName")
            // still matches instead of becoming "\xEF\xBB\xBFContactName".
            $header = $worksheet->rangeToArray("A1:AZ1", null, true, false)[0];
            $headerMap = array_flip(array_map(
                fn($v) => trim(str_replace("\xEF\xBB\xBF", '', $v ?? '')),
                $header
            ));

            // CRITICAL DIAGNOSTIC: shows exactly what headers were parsed.
            // If you see ONE long key like "ContactName EmailAddress POAddressLine1..."
            // then the delimiter was misread (tab file read as comma) -> see note in chat.
            \Log::info('Parsed header keys', [
                'count' => count($headerMap),
                'keys' => array_values(array_filter(array_keys($headerMap), fn($k) => $k !== '')),
            ]);

            $isInvoiceCsv = isset($headerMap['merchantName'], $headerMap['customerName'], $headerMap['processorName'], $headerMap['state']);
            $isNewCsv     = !$isInvoiceCsv && isset($headerMap['state']);
            // Xero invoice export (ContactName, EmailAddress, InvoiceNumber, line items, ...)
            $isXeroInvoiceCsv = !$isInvoiceCsv && !$isNewCsv
                && isset($headerMap['ContactName'], $headerMap['InvoiceNumber']);
            // Otherwise: old XLSX format

            $detectedFormat = $isInvoiceCsv ? 'invoiceCsv'
                : ($isNewCsv ? 'newCsv'
                : ($isXeroInvoiceCsv ? 'xeroInvoiceCsv'
                : 'legacyXlsx'));

            \Log::info('Detected format', [
                'format' => $detectedFormat,
                'isInvoiceCsv' => $isInvoiceCsv,
                'isNewCsv' => $isNewCsv,
                'isXeroInvoiceCsv' => $isXeroInvoiceCsv,
            ]);

            // Track merchant stats in memory
            $merchantStats = [];
            $skippedRows = 0;
            $loggedSamples = 0;

            // Set estimated total immediately
            $import->estimated_total = $highestRow - 1;
            $import->save();

            for ($startRow = 2; $startRow <= $highestRow; $startRow += $chunkSize) {
                $endRow = min($startRow + $chunkSize - 1, $highestRow);

                $memoryBefore = memory_get_usage(true);

                for ($row = $startRow; $row <= $endRow; $row++) {
                    try {
                        $rowData = $worksheet->rangeToArray("A{$row}:AZ{$row}", null, true, false)[0];

                        if (empty(array_filter($rowData))) {
                            continue;
                        }

                        // Skip header row
                        if ($rowData[0] === 'merchantName' || $rowData[0] === 'transactionId') {
                            continue;
                        }

                        // Detect format: new CSV format has state in column 3, old XLSX has it in column 41
                        // Map columns based on detected format
                        if ($isInvoiceCsv) {
                            $merchantName    = $rowData[$headerMap['merchantName']] ?? null;
                            $merchantId      = null;
                            $stateFromCsv    = $rowData[$headerMap['state']] ?? null;
                            $responseCode    = null;
                            $responseMessage = null;
                            $transactionId   = ($rowData[$headerMap['merchantName']] ?? '') . '_' . ($rowData[$headerMap['customerName']] ?? '') . '_' . $row;
                        } elseif ($isNewCsv) {
                            $merchantName    = $rowData[0] ?? null;
                            $merchantId      = null;
                            $stateFromCsv    = $rowData[3] ?? null;
                            $responseCode    = null;
                            $responseMessage = null;
                            $transactionId   = ($rowData[0] ?? '') . '_' . ($rowData[1] ?? '');
                        } elseif ($isXeroInvoiceCsv) {
                            // Invoice exports carry no transaction "state"; count each
                            // invoice (contact row) as one accepted transaction. Line-item
                            // rows have an empty ContactName and are skipped by the guard below.
                            $merchantName    = $rowData[$headerMap['ContactName']] ?? null;
                            $merchantId      = null;
                            $stateFromCsv    = 'accepted';
                            $responseCode    = null;
                            $responseMessage = null;
                            $transactionId   = $rowData[$headerMap['InvoiceNumber']] ?? null;
                        } else {
                            $merchantName    = $rowData[6] ?? null;
                            $merchantId      = $rowData[5] ?? null;
                            $stateFromCsv    = $rowData[41] ?? null;
                            $responseCode    = $rowData[42] ?? null;
                            $responseMessage = $rowData[43] ?? null;
                            $transactionId   = $rowData[0] ?? null;
                        }

                        // Log the first few mapped rows so we can see what the mapping produced.
                        if ($loggedSamples < 5) {
                            \Log::info('Row sample', [
                                'row' => $row,
                                'merchant_name' => $merchantName,
                                'transaction_id' => $transactionId,
                                'state_from_csv' => $stateFromCsv,
                            ]);
                            $loggedSamples++;
                        }

                        if (!$transactionId || !$merchantName) {
                            $skippedRows++;
                            continue;
                        }

                        // Determine state
                        if (!empty($stateFromCsv)) {
                            $state = strtolower(trim($stateFromCsv));
                        } else {
                            $state = CardstreamTransactionSummary::determineState($responseMessage, $responseCode);
                        }

                        // Normalize state names
                        $validStates = ['accepted', 'received', 'declined', 'canceled'];
                        if (!in_array($state, $validStates)) {
                            \Log::warning('Invalid state, defaulting to received', [
                                'state' => $state,
                                'merchant' => $merchantName,
                                'transaction_id' => $transactionId,
                            ]);
                            $state = 'received';
                        }

                        // Aggregate by merchant
                        $key = $merchantName;

                        if (!isset($merchantStats[$key])) {
                            $merchantStats[$key] = [
                                'merchant_id' => $merchantId,
                                'merchant_name' => $merchantName,
                                'total_transactions' => 0,
                                'accepted' => 0,
                                'received' => 0,
                                'declined' => 0,
                                'canceled' => 0,
                            ];
                        }

                        // Increment counters
                        $merchantStats[$key]['total_transactions']++;
                        $merchantStats[$key][$state]++;

                        $processedCount++;

                    } catch (\Throwable $e) {
                        // \Throwable (not \Exception) so a TypeError/Error on a single
                        // misdetected row is logged and skipped instead of killing the job.
                        \Log::error('Error processing row', [
                            'row' => $row,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }

                // Update progress every chunk
                $import->processed_rows = $processedCount;
                $import->save();

                \Log::info('Chunk processed', [
                    'rows' => "{$startRow}-{$endRow}",
                    'processed_so_far' => $processedCount,
                    'skipped_so_far' => $skippedRows,
                    'merchants_so_far' => count($merchantStats),
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);

                // Clear memory
                unset($rowData);
                gc_collect_cycles();

                $memoryAfter = memory_get_usage(true);
            }

            \Log::info('Row loop complete', [
                'processed' => $processedCount,
                'skipped' => $skippedRows,
                'distinct_merchants' => count($merchantStats),
            ]);

            // Free up spreadsheet memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet);
            gc_collect_cycles();

            // Save aggregated stats to database in batches
            DB::beginTransaction();

            try {
                $batchSize = 50;
                $batch = [];
                $insertedRows = 0;

                foreach ($merchantStats as $stats) {
                    $batch[] = [
                        'import_id' => $import->id,
                        'merchant_id' => $stats['merchant_id'],
                        'merchant_name' => $stats['merchant_name'],
                        'total_transactions' => $stats['total_transactions'],
                        'accepted' => $stats['accepted'],
                        'received' => $stats['received'],
                        'declined' => $stats['declined'],
                        'canceled' => $stats['canceled'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= $batchSize) {
                        CardstreamTransactionSummary::insert($batch);
                        $insertedRows += count($batch);
                        $batch = [];
                        gc_collect_cycles();
                    }
                }

                // Insert remaining batch
                if (!empty($batch)) {
                    CardstreamTransactionSummary::insert($batch);
                    $insertedRows += count($batch);
                }

                DB::commit();

                \Log::info('Merchant statistics saved', ['inserted_rows' => $insertedRows]);

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Failed to save merchant statistics', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            $import->total_rows = $processedCount;
            $import->status = 'completed';
            $import->save();

            \Log::info('Import completed', [
                'import_id' => $import->id,
                'total_rows' => $processedCount,
            ]);

            @unlink($this->filePath);

        } catch (\Throwable $e) {
            // \Throwable so a top-level TypeError/Error is captured here instead of
            // dying silently and only surfacing via failed().
            \Log::error('Import failed', [
                'import_id' => $import->id,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            ]);

            $import->status = 'failed';
            $import->error_message = $e->getMessage();
            $import->save();

            @unlink($this->filePath);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Job failed permanently', [
            'import_id' => $this->importId,
            'error_class' => get_class($exception),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $import = CardstreamImport::find($this->importId);
        if ($import) {
            $import->status = 'failed';
            $import->error_message = $exception->getMessage();
            $import->save();
        }
    }
}