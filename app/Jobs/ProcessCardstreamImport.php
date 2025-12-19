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
            $processedCount = 0;
            $chunkSize = 500; // Reduced from 1000 to 500 for better memory management
            
            // Track merchant stats in memory
            $merchantStats = [];
            
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

                        $transactionId = $rowData[0] ?? null;
                        $merchantId = $rowData[5] ?? null;
                        $merchantName = $rowData[6] ?? null;
                        $stateFromCsv = $rowData[41] ?? null;
                        $responseCode = $rowData[42] ?? null;
                        $responseMessage = $rowData[43] ?? null;

                        if (!$transactionId || !$merchantName) {
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
                        
                    } catch (\Exception $e) {
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
                
                // Clear memory
                unset($rowData);
                gc_collect_cycles();
                
                $memoryAfter = memory_get_usage(true);
            }
            
            // Free up spreadsheet memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet);
            gc_collect_cycles();
            
            // Save aggregated stats to database in batches
            DB::beginTransaction();
            
            try {
                $batchSize = 50;
                $batch = [];
                
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
                        $batch = [];
                        gc_collect_cycles();
                    }
                }
                
                // Insert remaining batch
                if (!empty($batch)) {
                    CardstreamTransactionSummary::insert($batch);
                }
                
                DB::commit();
                                
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
            
            @unlink($this->filePath);
            
        } catch (\Exception $e) {
            \Log::error('Import failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
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