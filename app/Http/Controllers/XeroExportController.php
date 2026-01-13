<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CardstreamImport;
use App\Models\CardstreamTransactionSummary;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Request;

class XeroExportController extends Controller
{
    /**
     * Export a single merchant's invoice to Xero CSV format
     */
    public function exportMerchantInvoice(string $merchantName): Response
    {
        $decodedMerchantName = urldecode($merchantName);
        
        // Get import_id from query string
        $importId = Request::input('import_id');
        
        if ($importId) {
            $import = CardstreamImport::where('id', $importId)
                ->where('status', 'completed')
                ->firstOrFail();
        } else {
            $import = CardstreamImport::where('status', 'completed')
                ->orderBy('imported_at', 'desc')
                ->firstOrFail();
        }
        
        // Get merchant stats
        $merchantStats = $import->getMerchantStats();
        $merchantStat = $merchantStats->firstWhere('merchant_name', $decodedMerchantName);
        
        if (!$merchantStat) {
            abort(404, 'Merchant not found in this import');
        }
        
        // Find matching account
        $accounts = Account::with('applications')->get();
        $account = $this->findMatchingAccount($accounts, $decodedMerchantName);
        
        if (!$account || $account->applications->isEmpty()) {
            abort(404, 'No application found for this merchant');
        }
        
        $application = $account->applications->first();
        
        // Get checkbox states from request
        $isFirstMonth = Request::input('is_first_month', false);
        $removeDeclineFee = Request::input('remove_decline_fee', false);
        $addChargebackFee = Request::input('add_chargeback_fee', false);
        
        // Generate CSV content
        $csv = $this->generateXeroInvoiceCSV(
            $account,
            $merchantStat,
            $application,
            $import,
            $isFirstMonth,
            $removeDeclineFee,
            $addChargebackFee
        );
        
        // Generate filename
        $date = now()->format('Y-m-d');
        $filename = "invoice_{$decodedMerchantName}_{$date}.csv";
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
    
    /**
     * Export all merchants' invoices in a single CSV
     */
    public function exportAllMerchantsInvoices(): Response
    {
        $importId = Request::input('import_id');
        
        if (!$importId) {
            abort(400, 'Import ID is required');
        }
        
        $import = CardstreamImport::where('id', $importId)
            ->where('status', 'completed')
            ->firstOrFail();
        
        // Get all merchant stats
        $merchantStats = $import->getMerchantStats();
        
        // Get all accounts with applications
        $accounts = Account::with('applications')->get();
        
        // Generate CSV content for all merchants
        $csv = $this->generateBulkXeroInvoiceCSV($accounts, $merchantStats, $import);
        
        // Generate filename
        $date = now()->format('Y-m-d');
        $filename = "invoices_bulk_{$import->filename}_{$date}.csv";
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
    
    /**
     * Export selected merchants' invoices in a single CSV
     */
    public function exportSelectedMerchantsInvoices(): Response
    {
        $importId = Request::input('import_id');
        $merchantsJson = Request::input('merchants');
        
        if (!$importId) {
            abort(400, 'Import ID is required');
        }
        
        if (!$merchantsJson) {
            abort(400, 'Merchant selection is required');
        }
        
        // Decode the merchant names array
        $selectedMerchantNames = json_decode($merchantsJson, true);
        
        if (!is_array($selectedMerchantNames) || empty($selectedMerchantNames)) {
            abort(400, 'Invalid merchant selection');
        }
        
        $import = CardstreamImport::where('id', $importId)
            ->where('status', 'completed')
            ->firstOrFail();
        
        // Get all merchant stats
        $allMerchantStats = $import->getMerchantStats();
        
        // Filter to only selected merchants
        $merchantStats = $allMerchantStats->filter(function ($stat) use ($selectedMerchantNames) {
            return in_array($stat->merchant_name, $selectedMerchantNames);
        });
        
        if ($merchantStats->isEmpty()) {
            abort(404, 'No matching merchants found in this import');
        }
        
        // Get all accounts with applications
        $accounts = Account::with('applications')->get();
        
        // Generate CSV content for selected merchants
        $csv = $this->generateBulkXeroInvoiceCSV($accounts, $merchantStats, $import);
        
        // Generate filename
        $date = now()->format('Y-m-d');
        $count = $merchantStats->count();
        $filename = "invoices_selected_{$count}_merchants_{$date}.csv";
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
    
    /**
     * Generate Xero CSV format for a single merchant
     */
    private function generateXeroInvoiceCSV(
        Account $account,
        CardstreamTransactionSummary $merchantStat,
        $application,
        CardstreamImport $import,
        bool $isFirstMonth,
        bool $removeDeclineFee,
        bool $addChargebackFee
    ): string {
        $TAX_RATE = 0.20; // 20% VAT
        
        // Calculate fees
        $transactionFeeQty = $merchantStat->accepted;
        $transactionFeePrice = (float) ($application->transaction_fixed_fee ?? 0);
        $transactionFeeSubtotal = $transactionFeeQty * $transactionFeePrice;
        
        // Monthly Mini Top Up calculation
        $scalingFee = (float) ($application->scaling_fee ?? 0);
        $monthlyMinimum = (float) ($application->monthly_minimum ?? 0);
        
        if ($isFirstMonth) {
            $monthlyMiniTopUpBasePrice = $monthlyMinimum;
        } else {
            $monthlyMiniTopUpBasePrice = $scalingFee === 0 ? $monthlyMinimum : $scalingFee;
        }
        
        $monthlyMiniTopUpPrice = max(0, $monthlyMiniTopUpBasePrice - $transactionFeeSubtotal);
        
        // Decline Fee
        $declineFeePrice = $merchantStat->declined * 0.10;
        
        // Monthly Fee
        $monthlyFeePrice = (float) ($application->monthly_fee ?? 0);
        
        // Invoice metadata
        $invoiceNumber = 'INV-' . str_pad($import->id, 4, '0', STR_PAD_LEFT);
        $issueDate = now()->format('d/m/Y');
        $dueDate = now()->format('d/m/Y');
        
        // Start CSV generation
        $output = fopen('php://temp', 'r+');
        
        // Write header row (Xero's required format)
        fputcsv($output, [
            'ContactName',
            'EmailAddress',
            'POAddressLine1',
            'POAddressLine2',
            'POAddressLine3',
            'POAddressLine4',
            'POCity',
            'PORegion',
            'POPostalCode',
            'POCountry',
            'InvoiceNumber',
            'Reference',
            'InvoiceDate',
            'DueDate',
            'Total',
            'TaxTotal',
            'InvoiceAmountPaid',
            'InvoiceAmountDue',
            'InventoryItemCode',
            'Description',
            'Quantity',
            'UnitAmount',
            'Discount',
            'AccountCode',
            'TaxType',
            'TaxAmount',
            'TrackingName1',
            'TrackingOption1',
            'TrackingName2',
            'TrackingOption2',
            'Currency',
            'BrandingTheme'
        ]);
        
        $rows = [];
        
        // Transaction Fee Row
        $rows[] = [
            'ContactName' => $account->name,
            'EmailAddress' => $account->email,
            'POAddressLine1' => '',
            'POAddressLine2' => '',
            'POAddressLine3' => '',
            'POAddressLine4' => '',
            'POCity' => '',
            'PORegion' => '',
            'POPostalCode' => '',
            'POCountry' => '',
            'InvoiceNumber' => $invoiceNumber,
            'Reference' => '',
            'InvoiceDate' => $issueDate,
            'DueDate' => $dueDate,
            'Total' => '', // Will be calculated by Xero
            'TaxTotal' => '', // Will be calculated by Xero
            'InvoiceAmountPaid' => '',
            'InvoiceAmountDue' => '',
            'InventoryItemCode' => '004',
            'Description' => 'Transaction Fee',
            'Quantity' => $transactionFeeQty,
            'UnitAmount' => number_format($transactionFeePrice, 2, '.', ''),
            'Discount' => '',
            'AccountCode' => '200',
            'TaxType' => '20% (VAT on Income)',
            'TaxAmount' => number_format($transactionFeeSubtotal * $TAX_RATE, 2, '.', ''),
            'TrackingName1' => '',
            'TrackingOption1' => '',
            'TrackingName2' => '',
            'TrackingOption2' => '',
            'Currency' => 'GBP',
            'BrandingTheme' => 'Standard'
        ];
        
        // Monthly Mini Top Up Row
        $rows[] = [
            'ContactName' => $account->name,
            'EmailAddress' => '',
            'POAddressLine1' => '',
            'POAddressLine2' => '',
            'POAddressLine3' => '',
            'POAddressLine4' => '',
            'POCity' => '',
            'PORegion' => '',
            'POPostalCode' => '',
            'POCountry' => '',
            'InvoiceNumber' => $invoiceNumber,
            'Reference' => '',
            'InvoiceDate' => '',
            'DueDate' => '',
            'Total' => '',
            'TaxTotal' => '',
            'InvoiceAmountPaid' => '',
            'InvoiceAmountDue' => '',
            'InventoryItemCode' => '',
            'Description' => 'Monthly Mini Top Up',
            'Quantity' => '1',
            'UnitAmount' => number_format($monthlyMiniTopUpPrice, 2, '.', ''),
            'Discount' => '',
            'AccountCode' => '200',
            'TaxType' => '20% (VAT on Income)',
            'TaxAmount' => number_format($monthlyMiniTopUpPrice * $TAX_RATE, 2, '.', ''),
            'TrackingName1' => '',
            'TrackingOption1' => '',
            'TrackingName2' => '',
            'TrackingOption2' => '',
            'Currency' => '',
            'BrandingTheme' => ''
        ];
        
        // Decline Fee Row (if not removed)
        if (!$removeDeclineFee && $merchantStat->declined > 0) {
            $rows[] = [
                'ContactName' => $account->name,
                'EmailAddress' => '',
                'POAddressLine1' => '',
                'POAddressLine2' => '',
                'POAddressLine3' => '',
                'POAddressLine4' => '',
                'POCity' => '',
                'PORegion' => '',
                'POPostalCode' => '',
                'POCountry' => '',
                'InvoiceNumber' => $invoiceNumber,
                'Reference' => '',
                'InvoiceDate' => '',
                'DueDate' => '',
                'Total' => '',
                'TaxTotal' => '',
                'InvoiceAmountPaid' => '',
                'InvoiceAmountDue' => '',
                'InventoryItemCode' => '005',
                'Description' => 'Decline Fee',
                'Quantity' => $merchantStat->declined,
                'UnitAmount' => '0.10',
                'Discount' => '',
                'AccountCode' => '200',
                'TaxType' => '20% (VAT on Income)',
                'TaxAmount' => number_format($declineFeePrice * $TAX_RATE, 2, '.', ''),
                'TrackingName1' => '',
                'TrackingOption1' => '',
                'TrackingName2' => '',
                'TrackingOption2' => '',
                'Currency' => '',
                'BrandingTheme' => ''
            ];
        }
        
        // Chargeback Fee Row (if added)
        if ($addChargebackFee) {
            $chargebackFeePrice = 15.00;
            $rows[] = [
                'ContactName' => $account->name,
                'EmailAddress' => '',
                'POAddressLine1' => '',
                'POAddressLine2' => '',
                'POAddressLine3' => '',
                'POAddressLine4' => '',
                'POCity' => '',
                'PORegion' => '',
                'POPostalCode' => '',
                'POCountry' => '',
                'InvoiceNumber' => $invoiceNumber,
                'Reference' => '',
                'InvoiceDate' => '',
                'DueDate' => '',
                'Total' => '',
                'TaxTotal' => '',
                'InvoiceAmountPaid' => '',
                'InvoiceAmountDue' => '',
                'InventoryItemCode' => '',
                'Description' => 'Chargeback Fee',
                'Quantity' => '1',
                'UnitAmount' => '15.00',
                'Discount' => '',
                'AccountCode' => '200',
                'TaxType' => '20% (VAT on Income)',
                'TaxAmount' => number_format($chargebackFeePrice * $TAX_RATE, 2, '.', ''),
                'TrackingName1' => '',
                'TrackingOption1' => '',
                'TrackingName2' => '',
                'TrackingOption2' => '',
                'Currency' => '',
                'BrandingTheme' => ''
            ];
        }
        
        // Monthly Fee Row
        $rows[] = [
            'ContactName' => $account->name,
            'EmailAddress' => '',
            'POAddressLine1' => '',
            'POAddressLine2' => '',
            'POAddressLine3' => '',
            'POAddressLine4' => '',
            'POCity' => '',
            'PORegion' => '',
            'POPostalCode' => '',
            'POCountry' => '',
            'InvoiceNumber' => $invoiceNumber,
            'Reference' => '',
            'InvoiceDate' => '',
            'DueDate' => '',
            'Total' => '',
            'TaxTotal' => '',
            'InvoiceAmountPaid' => '',
            'InvoiceAmountDue' => '',
            'InventoryItemCode' => '003',
            'Description' => 'Monthly Fee',
            'Quantity' => '1',
            'UnitAmount' => number_format($monthlyFeePrice, 2, '.', ''),
            'Discount' => '',
            'AccountCode' => '200',
            'TaxType' => '20% (VAT on Income)',
            'TaxAmount' => number_format($monthlyFeePrice * $TAX_RATE, 2, '.', ''),
            'TrackingName1' => '',
            'TrackingOption1' => '',
            'TrackingName2' => '',
            'TrackingOption2' => '',
            'Currency' => '',
            'BrandingTheme' => ''
        ];
        
        // Write all rows
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Generate bulk Xero CSV for all merchants
     */
    private function generateBulkXeroInvoiceCSV($accounts, $merchantStats, CardstreamImport $import): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Write header row
        fputcsv($output, [
            'ContactName',
            'EmailAddress',
            'POAddressLine1',
            'POAddressLine2',
            'POAddressLine3',
            'POAddressLine4',
            'POCity',
            'PORegion',
            'POPostalCode',
            'POCountry',
            'InvoiceNumber',
            'Reference',
            'InvoiceDate',
            'DueDate',
            'Total',
            'TaxTotal',
            'InvoiceAmountPaid',
            'InvoiceAmountDue',
            'InventoryItemCode',
            'Description',
            'Quantity',
            'UnitAmount',
            'Discount',
            'AccountCode',
            'TaxType',
            'TaxAmount',
            'TrackingName1',
            'TrackingOption1',
            'TrackingName2',
            'TrackingOption2',
            'Currency',
            'BrandingTheme'
        ]);
        
        $TAX_RATE = 0.20;
        $invoiceCounter = 1;
        
        foreach ($merchantStats as $merchantStat) {
            // Only export merchants with monthly_fee (matching accounts)
            $account = $this->findMatchingAccount($accounts, $merchantStat->merchant_name);
            
            if (!$account || $account->applications->isEmpty()) {
                continue;
            }
            
            $application = $account->applications->first();
            $monthlyFee = (float) ($application->monthly_fee ?? 0);
            
            if ($monthlyFee <= 0) {
                continue;
            }
            
            // Calculate fees (default: not first month, include decline fee, no chargeback)
            $transactionFeeQty = $merchantStat->total_transactions;
            $transactionFeePrice = (float) ($application->transaction_fixed_fee ?? 0);
            $transactionFeeSubtotal = $transactionFeeQty * $transactionFeePrice;
            
            $scalingFee = (float) ($application->scaling_fee ?? 0);
            $monthlyMinimum = (float) ($application->monthly_minimum ?? 0);
            $monthlyMiniTopUpBasePrice = $scalingFee === 0 ? $monthlyMinimum : $scalingFee;
            $monthlyMiniTopUpPrice = max(0, $monthlyMiniTopUpBasePrice - $transactionFeeSubtotal);
            
            $declineFeeQty = $merchantStat->declined + $merchantStat->received;
            $declineFeePrice = $declineFeeQty * 0.10;
            
            $invoiceNumber = 'INV-' . str_pad($import->id, 4, '0', STR_PAD_LEFT) . '-' . str_pad($invoiceCounter++, 3, '0', STR_PAD_LEFT);
            $issueDate = now()->format('d/m/Y');
            $dueDate = now()->format('d/m/Y');
            
            // Transaction Fee Row
            fputcsv($output, [
                'ContactName' => $account->name,
                'EmailAddress' => $account->email,
                'POAddressLine1' => '',
                'POAddressLine2' => '',
                'POAddressLine3' => '',
                'POAddressLine4' => '',
                'POCity' => '',
                'PORegion' => '',
                'POPostalCode' => '',
                'POCountry' => '',
                'InvoiceNumber' => $invoiceNumber,
                'Reference' => '',
                'InvoiceDate' => $issueDate,
                'DueDate' => $dueDate,
                'Total' => '',
                'TaxTotal' => '',
                'InvoiceAmountPaid' => '',
                'InvoiceAmountDue' => '',
                'InventoryItemCode' => '004',
                'Description' => 'Transaction Fee',
                'Quantity' => $transactionFeeQty,
                'UnitAmount' => number_format($transactionFeePrice, 2, '.', ''),
                'Discount' => '',
                'AccountCode' => '200',
                'TaxType' => '20% (VAT on Income)',
                'TaxAmount' => number_format($transactionFeeSubtotal * $TAX_RATE, 2, '.', ''),
                'TrackingName1' => '',
                'TrackingOption1' => '',
                'TrackingName2' => '',
                'TrackingOption2' => '',
                'Currency' => 'GBP',
                'BrandingTheme' => 'Standard'
            ]);
            
            // Monthly Mini Top Up (only if there's a shortfall)
            if ($monthlyMiniTopUpPrice > 0) {
                fputcsv($output, [
                    $account->name, '', '', '', '', '', '', '', '', '',
                    $invoiceNumber, '', '', '', '', '', '', '',
                    '',
                    'Monthly Mini Top Up',
                    '1',
                    number_format($monthlyMiniTopUpPrice, 2, '.', ''),
                    '',
                    '200',
                    '20% (VAT on Income)',
                    number_format($monthlyMiniTopUpPrice * $TAX_RATE, 2, '.', ''),
                    '', '', '', '', '', ''
                ]);
            }
            
            // Decline Fee (if any)
            if ($declineFeeQty > 0) {
                fputcsv($output, [
                    $account->name, '', '', '', '', '', '', '', '', '',
                    $invoiceNumber, '', '', '', '', '', '', '',
                    '005',
                    'Decline Fee',
                    $declineFeeQty,
                    '0.10',
                    '',
                    '200',
                    '20% (VAT on Income)',
                    number_format($declineFeePrice * $TAX_RATE, 2, '.', ''),
                    '', '', '', '', '', ''
                ]);
            }
            
            // Monthly Fee
            fputcsv($output, [
                $account->name, '', '', '', '', '', '', '', '', '',
                $invoiceNumber, '', '', '', '', '', '', '',
                '003',
                'Monthly Fee',
                '1',
                number_format($monthlyFee, 2, '.', ''),
                '',
                '200',
                '20% (VAT on Income)',
                number_format($monthlyFee * $TAX_RATE, 2, '.', ''),
                '', '', '', '', '', ''
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Find matching account using fuzzy matching logic
     */
    private function findMatchingAccount($accounts, string $merchantName)
    {
        // Try exact match first
        $account = $accounts->firstWhere('name', $merchantName);
        
        if ($account) {
            return $account;
        }
        
        // Fuzzy matching
        $bestMatch = null;
        $highestScore = 0;
        $threshold = 80;
        
        $searchName = strtolower(trim($merchantName));
        $searchNameClean = preg_replace('/\b(ltd|limited|competitions?|comps?)\b/i', '', $searchName);
        $searchNameClean = preg_replace('/\s+/', ' ', trim($searchNameClean));
        
        foreach ($accounts as $acc) {
            $accountName = strtolower(trim($acc->name));
            $accountNameClean = preg_replace('/\b(ltd|limited|competitions?|comps?)\b/i', '', $accountName);
            $accountNameClean = preg_replace('/\s+/', ' ', trim($accountNameClean));
            
            similar_text($searchNameClean, $accountNameClean, $score);
            
            $containsScore = 0;
            if (strlen($searchNameClean) >= 5 && strlen($accountNameClean) >= 5) {
                if (str_contains($accountNameClean, $searchNameClean)) {
                    $containsScore = 90;
                } elseif (str_contains($searchNameClean, $accountNameClean)) {
                    $containsScore = 90;
                }
            }
            
            $finalScore = max($score, $containsScore);
            
            if ($finalScore > $highestScore && $finalScore >= $threshold) {
                $highestScore = $finalScore;
                $bestMatch = $acc;
            }
        }
        
        return $bestMatch;
    }
}