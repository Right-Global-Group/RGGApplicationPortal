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

    public $timeout = 1800;
    public $tries = 1;

    public function __construct(
        public int $importId,
        public string $filePath
    ) {}

    public function handle(): void
    {
        \Log::info('ProcessCardstreamImport v3 starting', [
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
            $merchantStats = [];
            $processedCount = 0;
            $skippedRows = 0;
            $chunkSize = 500;

            $isCsv = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION)) === 'csv';

            if ($isCsv) {
                // CSV path: fgetcsv streams row-by-row — negligible memory vs PhpSpreadsheet's ~3 GB load
                $handle = fopen($this->filePath, 'r');
                if ($handle === false) {
                    throw new \RuntimeException("Failed to open CSV file: {$this->filePath}");
                }

                $headerRaw = fgetcsv($handle) ?: [];
                $headerMap = array_flip(array_map(
                    fn($v) => trim(str_replace("\xEF\xBB\xBF", '', $v ?? '')),
                    $headerRaw
                ));

                \Log::info('Parsed header keys (CSV)', [
                    'count' => count($headerMap),
                    'keys' => array_values(array_filter(array_keys($headerMap), fn($k) => $k !== '')),
                ]);

                $isInvoiceCsv    = isset($headerMap['merchantName'], $headerMap['customerName'], $headerMap['processorName'], $headerMap['state']);
                $isNewCsv        = !$isInvoiceCsv && isset($headerMap['state']);
                $isXeroInvoiceCsv = !$isInvoiceCsv && !$isNewCsv
                    && isset($headerMap['ContactName'], $headerMap['InvoiceNumber']);

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

                $loggedSamples = 0;
                $row = 1;
                $chunkStart = 2;

                while (($rowData = fgetcsv($handle)) !== false) {
                    $row++;

                    try {
                        if (empty(array_filter($rowData))) {
                            continue;
                        }

                        if ($rowData[0] === 'merchantName' || $rowData[0] === 'transactionId') {
                            continue;
                        }

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

                        if (!empty($stateFromCsv)) {
                            $state = strtolower(trim($stateFromCsv));
                        } else {
                            $state = CardstreamTransactionSummary::determineState($responseMessage, $responseCode);
                        }

                        $validStates = ['accepted', 'received', 'declined', 'canceled'];
                        if (!in_array($state, $validStates)) {
                            \Log::warning('Invalid state, defaulting to received', [
                                'state' => $state,
                                'merchant' => $merchantName,
                                'transaction_id' => $transactionId,
                            ]);
                            $state = 'received';
                        }

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

                        $merchantStats[$key]['total_transactions']++;
                        $merchantStats[$key][$state]++;
                        $processedCount++;

                        if ($processedCount % $chunkSize === 0) {
                            $import->processed_rows = $processedCount;
                            $import->save();

                            \Log::info('Chunk processed', [
                                'rows' => "{$chunkStart}-{$row}",
                                'processed_so_far' => $processedCount,
                                'skipped_so_far' => $skippedRows,
                                'merchants_so_far' => count($merchantStats),
                                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                            ]);

                            $chunkStart = $row + 1;
                            gc_collect_cycles();
                        }

                    } catch (\Throwable $e) {
                        \Log::error('Error processing row', [
                            'row' => $row,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }

                fclose($handle);

            } else {
                // XLSX path: PhpSpreadsheet required for binary formats
                $spreadsheet = IOFactory::load($this->filePath);
                $worksheet = $spreadsheet->getActiveSheet();

                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();

                \Log::info('Spreadsheet loaded', [
                    'highest_row' => $highestRow,
                    'highest_column' => $highestColumn,
                ]);

                $header = $worksheet->rangeToArray("A1:AZ1", null, true, false)[0];
                $headerMap = array_flip(array_map(
                    fn($v) => trim(str_replace("\xEF\xBB\xBF", '', $v ?? '')),
                    $header
                ));

                \Log::info('Parsed header keys', [
                    'count' => count($headerMap),
                    'keys' => array_values(array_filter(array_keys($headerMap), fn($k) => $k !== '')),
                ]);

                $isInvoiceCsv    = isset($headerMap['merchantName'], $headerMap['customerName'], $headerMap['processorName'], $headerMap['state']);
                $isNewCsv        = !$isInvoiceCsv && isset($headerMap['state']);
                $isXeroInvoiceCsv = !$isInvoiceCsv && !$isNewCsv
                    && isset($headerMap['ContactName'], $headerMap['InvoiceNumber']);

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

                $loggedSamples = 0;

                $import->estimated_total = $highestRow - 1;
                $import->save();

                for ($startRow = 2; $startRow <= $highestRow; $startRow += $chunkSize) {
                    $endRow = min($startRow + $chunkSize - 1, $highestRow);

                    for ($row = $startRow; $row <= $endRow; $row++) {
                        try {
                            $rowData = $worksheet->rangeToArray("A{$row}:AZ{$row}", null, true, false)[0];

                            if (empty(array_filter($rowData))) {
                                continue;
                            }

                            if ($rowData[0] === 'merchantName' || $rowData[0] === 'transactionId') {
                                continue;
                            }

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

                            if (!empty($stateFromCsv)) {
                                $state = strtolower(trim($stateFromCsv));
                            } else {
                                $state = CardstreamTransactionSummary::determineState($responseMessage, $responseCode);
                            }

                            $validStates = ['accepted', 'received', 'declined', 'canceled'];
                            if (!in_array($state, $validStates)) {
                                \Log::warning('Invalid state, defaulting to received', [
                                    'state' => $state,
                                    'merchant' => $merchantName,
                                    'transaction_id' => $transactionId,
                                ]);
                                $state = 'received';
                            }

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

                            $merchantStats[$key]['total_transactions']++;
                            $merchantStats[$key][$state]++;
                            $processedCount++;

                        } catch (\Throwable $e) {
                            \Log::error('Error processing row', [
                                'row' => $row,
                                'error' => $e->getMessage(),
                            ]);
                            continue;
                        }
                    }

                    $import->processed_rows = $processedCount;
                    $import->save();

                    \Log::info('Chunk processed', [
                        'rows' => "{$startRow}-{$endRow}",
                        'processed_so_far' => $processedCount,
                        'skipped_so_far' => $skippedRows,
                        'merchants_so_far' => count($merchantStats),
                        'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    ]);

                    unset($rowData);
                    gc_collect_cycles();
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $worksheet);
                gc_collect_cycles();
            }

            \Log::info('Row loop complete', [
                'processed' => $processedCount,
                'skipped' => $skippedRows,
                'distinct_merchants' => count($merchantStats),
            ]);

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
