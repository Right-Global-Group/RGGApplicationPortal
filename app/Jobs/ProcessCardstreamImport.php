<?php

namespace App\Jobs;

use App\Models\CardstreamImport;
use App\Models\CardstreamTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessCardstreamImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 1;

    public function __construct(
        public CardstreamImport $import,
        public string $filePath
    ) {}

    public function handle(): void
    {
        try {
            // Use IOFactory to automatically detect file type (CSV, XLSX, XLS)
            $spreadsheet = IOFactory::load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $worksheet->getHighestRow();
            $processedCount = 0;
            $chunkSize = 1000; // Process 1000 rows at a time
            
            \Log::info('Starting import processing', [
                'import_id' => $this->import->id,
                'filename' => $this->import->filename,
                'total_rows' => $highestRow,
            ]);
            
            // Skip header row (start from row 2)
            for ($startRow = 2; $startRow <= $highestRow; $startRow += $chunkSize) {
                $endRow = min($startRow + $chunkSize - 1, $highestRow);
                
                \Log::info('Processing chunk', [
                    'start_row' => $startRow,
                    'end_row' => $endRow,
                ]);
                
                DB::beginTransaction();
                
                try {
                    $transactions = [];
                    
                    for ($row = $startRow; $row <= $endRow; $row++) {
                        // Get entire row - adjust range if you have more columns
                        $rowData = $worksheet->rangeToArray("A{$row}:AZ{$row}", null, true, false)[0];
                        
                        // Skip empty rows
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

                        // Skip if essential data is missing
                        if (!$transactionId || !$merchantName) {
                            continue;
                        }

                        // Determine state
                        if (!empty($stateFromCsv)) {
                            $state = strtolower($stateFromCsv);
                        } else {
                            $state = CardstreamTransaction::determineState($responseMessage, $responseCode);
                        }

                        // Parse transaction date
                        try {
                            if (is_numeric($transactionDate)) {
                                // Excel numeric date format
                                $transactionDateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($transactionDate);
                            } else {
                                // String date format (CSV)
                                $transactionDateTime = new \DateTime($transactionDate);
                            }
                        } catch (\Exception $e) {
                            $transactionDateTime = new \DateTime();
                        }

                        $transactions[] = [
                            'import_id' => $this->import->id,
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
                    
                    // Bulk insert for better performance
                    if (!empty($transactions)) {
                        CardstreamTransaction::insert($transactions);
                        \Log::info('Inserted chunk', [
                            'count' => count($transactions),
                            'total_processed' => $processedCount,
                        ]);
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
                
                // Clear memory
                unset($transactions);
                
                // Update progress
                $this->import->update([
                    'processed_rows' => $processedCount,
                    'estimated_total' => $highestRow - 1, // Set once at the start
                    'status' => 'processing',
                ]);
            }
            
            // Mark as complete
            $this->import->update([
                'total_rows' => $processedCount,
                'status' => 'completed',
            ]);
            
            \Log::info('Import completed successfully', [
                'import_id' => $this->import->id,
                'total_rows' => $processedCount,
            ]);
            
            // Clean up file
            @unlink($this->filePath);
            
        } catch (\Exception $e) {
            \Log::error('Import failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            // Clean up file
            @unlink($this->filePath);
            
            throw $e;
        }
    }
}