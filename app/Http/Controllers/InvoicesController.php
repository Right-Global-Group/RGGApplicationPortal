<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CardstreamImport;
use App\Models\CardstreamTransaction;
use App\Jobs\ProcessCardstreamImport;
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
                // Get merchant statistics from dedicated table
                $stats = $selectedImport->getMerchantStats();
                
                // Get all accounts with their first application
                $accounts = Account::with('applications')->get();

                $merchantStats = $stats->map(function ($stat) use ($accounts) {
                    $monthlyFee = null;
                    $monthlyMinimum = null;
                    $scalingFee = null;
                    
                    // Try exact match first (fastest)
                    $account = $accounts->firstWhere('name', $stat->merchant_name);
                    
                    // If no exact match, use more conservative fuzzy matching
                    if (!$account) {
                        $bestMatch = null;
                        $highestScore = 0;
                        $threshold = 80; // Increased from 70 to 80 for stricter matching
                        
                        $searchName = strtolower(trim($stat->merchant_name));
                        // Remove common suffixes for better matching
                        $searchNameClean = preg_replace('/\b(ltd|limited|competitions?|comps?)\b/i', '', $searchName);
                        $searchNameClean = preg_replace('/\s+/', ' ', trim($searchNameClean));
                        
                        foreach ($accounts as $acc) {
                            $accountName = strtolower(trim($acc->name));
                            $accountNameClean = preg_replace('/\b(ltd|limited|competitions?|comps?)\b/i', '', $accountName);
                            $accountNameClean = preg_replace('/\s+/', ' ', trim($accountNameClean));
                            
                            // Calculate similarity percentage on cleaned names
                            similar_text($searchNameClean, $accountNameClean, $score);
                            
                            // Require significant overlap for contains matches
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
                        
                        $account = $bestMatch;
                    }
                    
                    if ($account && $account->applications->isNotEmpty()) {
                        $monthlyFee = $account->applications->first()->monthly_fee;
                        $monthlyMinimum = $account->applications->first()->monthly_minimum;
                        $scalingFee = $account->applications->first()->scaling_fee;
                    }
                    
                    return [
                        'merchant_name' => $stat->merchant_name,
                        'merchant_id' => $stat->merchant_id,
                        'accepted' => $stat->accepted,
                        'received' => $stat->received,
                        'declined' => $stat->declined,
                        'canceled' => $stat->canceled,
                        'total_transactions' => $stat->total_transactions,
                        'monthly_fee' => $monthlyFee,
                        'monthly_minimum' => $monthlyMinimum ?? null,
                        'scaling_fee' => $scalingFee ?? null,
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
                'status' => $selectedImport->status,
                'processed_rows' => $selectedImport->processed_rows ?? 0,
                'estimated_total' => $selectedImport->estimated_total ?? 0,
            ] : null,
        ]);
    }

    public function show(string $merchantName): Response
    {
        $decodedMerchantName = urldecode($merchantName);
        
        // Get import_id from query string, or use most recent completed import
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
        
        // Get merchant stats for this merchant using the model's method
        $merchantStats = $import->getMerchantStats();
        $merchantStat = $merchantStats->firstWhere('merchant_name', $decodedMerchantName);
        
        if (!$merchantStat) {
            abort(404, 'Merchant not found in this import');
        }
        
        // Find matching account
        $accounts = Account::with('applications')->get();
        $account = $accounts->firstWhere('name', $decodedMerchantName);
        
        // If no exact match, use fuzzy matching
        if (!$account) {
            $bestMatch = null;
            $highestScore = 0;
            $threshold = 80;
            
            $searchName = strtolower(trim($decodedMerchantName));
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
            
            $account = $bestMatch;
        }
        
        if (!$account || $account->applications->isEmpty()) {
            abort(404, 'No application found for this merchant');
        }
        
        $application = $account->applications->first();
        
        return Inertia::render('Invoices/Show', [
            'merchantName' => $decodedMerchantName,
            'merchantStats' => [
                'accepted' => $merchantStat->accepted,
                'received' => $merchantStat->received,
                'declined' => $merchantStat->declined,
                'canceled' => $merchantStat->canceled,
                'total_transactions' => $merchantStat->total_transactions,
            ],
            'applicationData' => [
                'scaling_fee' => $application->scaling_fee,
                'transaction_percentage' => $application->transaction_percentage,
                'transaction_fixed_fee' => $application->transaction_fixed_fee,
                'monthly_fee' => $application->monthly_fee,
                'monthly_minimum' => $application->monthly_minimum,
                'setup_fee' => $application->setup_fee,
            ],
            'importFilename' => $import->filename,
            'importId' => $import->id,
        ]);
    }

    public function upload(): RedirectResponse
    {        
        Request::validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);

        $file = Request::file('file');
        $filename = $file->getClientOriginalName();

        try {
            $tempDir = storage_path('app/temp/imports');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $uniqueFilename = uniqid() . '_' . $filename;
            $fullPath = $tempDir . '/' . $uniqueFilename;
            
            $file->move($tempDir, $uniqueFilename);

            $import = CardstreamImport::create([
                'user_id' => auth()->id(),
                'filename' => $filename,
                'total_rows' => 0,
                'imported_at' => now(),
                'status' => 'pending',
            ]);

            ProcessCardstreamImport::dispatch($import->id, $fullPath);
            
            return Redirect::route('invoices.index', ['import_id' => $import->id])
                ->with('success', "Import started for {$filename}. Processing in background...");

        } catch (\Exception $e) {
            \Log::error('Upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Redirect::back()
                ->with('error', 'Failed to start import: ' . $e->getMessage());
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