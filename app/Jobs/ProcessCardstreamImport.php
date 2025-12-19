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
        \Log::info('=== JOB STARTED ===', ['import_id' => $this->importId]);
        
        $import = CardstreamImport::find($this->importId);
        
        if (!$import) {
            \Log::error('Import not found', ['import_id' => $this->importId]);
            return;
        }
    
        \Log::info('Setting status to processing', ['import_id' => $import->id]);
        $import->status = 'processing';
        $import->processed_rows = 0;
        $import->save();
        
        \Log::info('Status updated, current status:', ['status' => $import->fresh()->status]);
        
        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $worksheet->getHighestRow();
            $processedCount = 0;
            $chunkSize = 1000;
            
            // Track merchant stats in memory
            $merchantStats = [];
            
            // Set estimated total immediately
            $import->estimated_total = $highestRow - 1;
            $import->save();
            
            \Log::info('Starting import processing', [
                'import_id' => $import->id,
                'filename' => $import->filename,
                'total_rows' => $highestRow,
            ]);
            
            for ($startRow = 2; $startRow <= $highestRow; $startRow += $chunkSize) {
                $endRow = min($startRow + $chunkSize - 1, $highestRow);
                
                for ($row = $startRow; $row <= $endRow; $row++) {
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
                        $state = strtolower($stateFromCsv);
                    } else {
                        $state = CardstreamTransaction::determineState($responseMessage, $responseCode);
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

                    // Increment total transactions ONCE
                    $merchantStats[$key]['total_transactions']++;

                    // Increment the specific state counter if valid
                    if (isset($merchantStats[$key][$state])) {
                        $merchantStats[$key][$state]++;
                    } else {
                        \Log::warning('Unexpected state encountered', [
                            'state' => $state,
                            'merchant' => $merchantName,
                            'transaction_id' => $transactionId,
                        ]);
                    }
                    
                    $processedCount++;
                }
                
                // Update progress every chunk
                $import->processed_rows = $processedCount;
                $import->save();
                
                \Log::info('Processed chunk', [
                    'end_row' => $endRow,
                    'total_processed' => $processedCount,
                ]);
            }
            
            // Save aggregated stats to database
            DB::beginTransaction();
            
            try {
                foreach ($merchantStats as $stats) {
                    CardstreamTransactionSummary::create([
                        'import_id' => $import->id,
                        'merchant_id' => $stats['merchant_id'],
                        'merchant_name' => $stats['merchant_name'],
                        'total_transactions' => $stats['total_transactions'],
                        'accepted' => $stats['accepted'],
                        'received' => $stats['received'],
                        'declined' => $stats['declined'],
                        'canceled' => $stats['canceled'],
                    ]);
                }
                
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
            $import->total_rows = $processedCount;
            $import->status = 'completed';
            $import->save();
            
            \Log::info('Import completed successfully', [
                'import_id' => $import->id,
                'total_rows' => $processedCount,
                'merchants_count' => count($merchantStats),
            ]);
            
            @unlink($this->filePath);
            
        } catch (\Exception $e) {
            \Log::error('Import failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);
            
            $import->status = 'failed';
            $import->error_message = $e->getMessage();
            $import->save();
            
            @unlink($this->filePath);
            
            throw $e;
        }
    }
}