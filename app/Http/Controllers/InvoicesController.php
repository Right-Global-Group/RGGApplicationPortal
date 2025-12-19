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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);
    
        $file = Request::file('file');
        $filename = $file->getClientOriginalName();
    
        try {
            // Create temp directory if it doesn't exist
            $tempDir = storage_path('app/temp/imports');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
    
            // Generate unique filename and move file
            $uniqueFilename = uniqid() . '_' . $filename;
            $fullPath = $tempDir . '/' . $uniqueFilename;
            $file->move($tempDir, $uniqueFilename);
    
            // Create import record
            $import = CardstreamImport::create([
                'user_id' => auth()->id(),
                'filename' => $filename,
                'total_rows' => 0,
                'imported_at' => now(),
                'status' => 'pending',
            ]);
    
            // Dispatch job
            ProcessCardstreamImport::dispatch($import, $fullPath);
    
            return Redirect::route('invoices.index', ['import_id' => $import->id])
                ->with('success', "Import started for {$filename}. Processing in background...");
    
        } catch (\Exception $e) {
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