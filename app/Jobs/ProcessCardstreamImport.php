<?php

namespace App\Jobs;

use App\Models\CardstreamImport;
use App\Models\CardstreamTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
// REMOVE SerializesModels - it's causing the foreign key issue
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessCardstreamImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    // Removed SerializesModels

    public $timeout = 600;
    public $tries = 1;

    // Store import ID instead of the model
    public function __construct(
        public int $importId,
        public string $filePath
    ) {}

    public function handle(): void
    {
        // Load the import fresh from database
        $import = CardstreamImport::find($this->importId);
        
        if (!$import) {
            \Log::error('Import not found', ['import_id' => $this->importId]);
            return;
        }

        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $worksheet->getHighestRow();
            $processedCount = 0;
            $chunkSize = 1000;
            
            \Log::info('Starting import processing', [
                'import_id' => $import->id,
                'filename' => $import->filename,
                'total_rows' => $highestRow,
            ]);
            
            for ($startRow = 2; $startRow <= $highestRow; $startRow += $chunkSize) {
                $endRow = min($startRow + $chunkSize - 1, $highestRow);
                
                DB::beginTransaction();
                
                try {
                    $transactions = [];
                    
                    for ($row = $startRow; $row <= $endRow; $row++) {
                        $rowData = $worksheet->rangeToArray("A{$row}:AZ{$row}", null, true, false)[0];
                        
                        if (empty(array_filter($rowData))) {
                            continue;
                        }

                        $transactionId = $rowData[0] ?? null;
                        $transactionDate = $rowData[1] ?? null;
                        $merchantId = $rowData[5] ?? null;
                        $merchantName = $rowData[6] ?? null;
                        $action = $rowData[9] ?? null;
                        $currency = $rowData[12] ?? null;
                        $amount = $rowData[14] ?? 0;
                        $customerName = $rowData[24] ?? null;
                        $customerEmail = $rowData[26] ?? null;
                        $cardType = $rowData[28] ?? null;
                        $stateFromCsv = $rowData[41] ?? null;
                        $responseCode = $rowData[42] ?? null;
                        $responseMessage = $rowData[43] ?? null;

                        if (!$transactionId || !$merchantName) {
                            continue;
                        }

                        if (!empty($stateFromCsv)) {
                            $state = strtolower($stateFromCsv);
                        } else {
                            $state = CardstreamTransaction::determineState($responseMessage, $responseCode);
                        }

                        try {
                            if (is_numeric($transactionDate)) {
                                $transactionDateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($transactionDate);
                            } else {
                                $transactionDateTime = new \DateTime($transactionDate);
                            }
                        } catch (\Exception $e) {
                            $transactionDateTime = new \DateTime();
                        }

                        $transactions[] = [
                            'import_id' => $import->id,
                            'transaction_id' => $transactionId,
                            'transaction_date' => $transactionDateTime->format('Y-m-d H:i:s'),
                            'merchant_id' => $merchantId,
                            'merchant_name' => $merchantName,
                            'action' => $action,
                            'currency' => $currency,
                            'amount' => $amount,
                            'customer_name' => $customerName,
                            'customer_email' => $customerEmail,
                            'card_type' => $cardType,
                            'response_code' => $responseCode,
                            'response_message' => $responseMessage,
                            'state' => $state,
                            'raw_data' => json_encode($rowData),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        
                        $processedCount++;
                    }
                    
                    if (!empty($transactions)) {
                        CardstreamTransaction::insert($transactions);
                    }
                    
                    DB::commit();
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('Chunk processing failed', [
                        'start_row' => $startRow,
                        'end_row' => $endRow,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
                
                unset($transactions);
                
                // Refresh import from database and update
                $import = $import->fresh();
                $import->update([
                    'processed_rows' => $processedCount,
                    'estimated_total' => $highestRow - 1,
                    'status' => 'processing',
                ]);
            }
            
            $import->update([
                'total_rows' => $processedCount,
                'status' => 'completed',
            ]);
            
            \Log::info('Import completed successfully', [
                'import_id' => $import->id,
                'total_rows' => $processedCount,
            ]);
            
            @unlink($this->filePath);
            
        } catch (\Exception $e) {
            \Log::error('Import failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);
            
            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            @unlink($this->filePath);
            
            throw $e;
        }
    }
}