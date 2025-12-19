<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CardstreamImport;
use App\Models\CardstreamTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InvoicesController extends Controller
{
    public function index(): Response
    {
        // Get all imports with user information
        $imports = CardstreamImport::with('user')
            ->orderBy('imported_at', 'desc')
            ->get()
            ->map(fn ($import) => [
                'id' => $import->id,
                'filename' => $import->filename,
                'total_rows' => $import->total_rows,
                'imported_at' => $import->imported_at->format('Y-m-d H:i'),
                'user_name' => $import->user 
                    ? ($import->user->first_name . ' ' . $import->user->last_name)
                    : 'Unknown',
            ]);

        // Get selected import ID from request
        $selectedImportId = Request::input('import_id');
        
        $merchantStats = [];
        $selectedImport = null;

        if ($selectedImportId) {
            $selectedImport = CardstreamImport::find($selectedImportId);
            
            if ($selectedImport) {
                // Get merchant statistics
                $stats = $selectedImport->getMerchantStats();
                
                // Get all accounts with their first application to match merchant names with monthly fees
                $accounts = Account::with('applications')->get()->keyBy('name');
                
                $merchantStats = $stats->map(function ($stat) use ($accounts) {
                    // Try to find matching account by name
                    $account = $accounts->get($stat->merchant_name);
                    
                    // Get monthly_fee from first application if account exists
                    $monthlyFee = null;
                    if ($account && $account->applications->isNotEmpty()) {
                        $monthlyFee = $account->applications->first()->monthly_fee;
                    }
                    
                    return [
                        'merchant_name' => $stat->merchant_name,
                        'merchant_id' => $stat->merchant_id,
                        'accepted' => (int) $stat->accepted,
                        'received' => (int) $stat->received,
                        'declined' => (int) $stat->declined,
                        'canceled' => (int) $stat->canceled,
                        'total_transactions' => (int) $stat->total_transactions,
                        'monthly_fee' => $monthlyFee,
                    ];
                })->toArray();
            }
        }

        return Inertia::render('Invoices/Index', [
            'imports' => $imports,
            'selectedImportId' => $selectedImportId,
            'merchantStats' => $merchantStats,
            'selectedImport' => $selectedImport ? [
                'id' => $selectedImport->id,
                'filename' => $selectedImport->filename,
                'imported_at' => $selectedImport->imported_at->format('Y-m-d H:i'),
            ] : null,
        ]);
    }

    public function upload(): RedirectResponse
    {
        Request::validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'], // 10MB max
        ]);

        $file = Request::file('file');
        $filename = $file->getClientOriginalName();

        try {
            DB::beginTransaction();

            // Create import record
            $import = CardstreamImport::create([
                'user_id' => auth()->id(),
                'filename' => $filename,
                'total_rows' => 0,
                'imported_at' => now(),
            ]);

            // Process the file
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip header row
            $dataRows = array_slice($rows, 1);
            $processedCount = 0;

            foreach ($dataRows as $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Map columns based on your CSV structure
                // Adjust indices based on actual column positions
                $transactionId = $row[0] ?? null;
                $transactionDate = $row[1] ?? null;
                $merchantId = $row[5] ?? null;
                $merchantName = $row[6] ?? null;
                $action = $row[9] ?? null;
                $currency = $row[12] ?? null;
                $amount = $row[14] ?? 0;
                $customerName = $row[24] ?? null;
                $customerEmail = $row[26] ?? null;
                $cardType = $row[28] ?? null;
                $stateFromCsv = $row[41] ?? null; // State column in CSV
                $responseCode = $row[42] ?? null;
                $responseMessage = $row[43] ?? null;

                // Skip if essential data is missing
                if (!$transactionId || !$merchantName) {
                    continue;
                }

                // Determine state - use CSV state if available, otherwise calculate it
                if (!empty($stateFromCsv)) {
                    $state = strtolower($stateFromCsv);
                } else {
                    $state = CardstreamTransaction::determineState($responseMessage, $responseCode);
                }

                // Parse transaction date
                try {
                    // Check if it's a numeric Excel date or a string date
                    if (is_numeric($transactionDate)) {
                        // Excel numeric date format
                        $transactionDateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($transactionDate);
                    } else {
                        // String date format (CSV) - parse directly
                        $transactionDateTime = new \DateTime($transactionDate);
                    }
                } catch (\Exception $e) {
                    // If all parsing fails, use current time
                    $transactionDateTime = new \DateTime();
                }

                CardstreamTransaction::create([
                    'import_id' => $import->id,
                    'transaction_id' => $transactionId,
                    'transaction_date' => $transactionDateTime,
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
                    'raw_data' => $row,
                ]);

                $processedCount++;
            }

            // Update import with total rows
            $import->update(['total_rows' => $processedCount]);

            DB::commit();

            return Redirect::route('invoices.index', ['import_id' => $import->id])
                ->with('success', "Successfully imported {$processedCount} transactions from {$filename}");

        } catch (\Exception $e) {
            DB::rollBack();
            
            return Redirect::back()
                ->with('error', 'Failed to import file: ' . $e->getMessage());
        }
    }

    public function destroy(CardstreamImport $import): RedirectResponse
    {
        // Check if user is admin or the creator
        if (!auth()->user()->isAdmin() && $import->user_id !== auth()->id()) {
            abort(403, 'You can only delete imports you created.');
        }

        $import->delete();

        return Redirect::route('invoices.index')
            ->with('success', 'Import deleted successfully.');
    }
}