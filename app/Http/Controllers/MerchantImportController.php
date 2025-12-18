<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\MerchantImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class MerchantImportController extends Controller
{
    /**
     * Get all import history
     */
    public function index(): Response
    {
        $imports = MerchantImport::with(['user', 'account', 'application'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($import) => [
                'id' => $import->id,
                'merchant_name' => $import->merchant_name,
                'account_id' => $import->account_id,
                'application_id' => $import->application_id,
                'uploaded_by' => $import->user->name ?? 'Unknown',
                'status' => $import->status,
                'error_message' => $import->error_message,
                'created_at' => $import->created_at->format('Y-m-d H:i'),
                'account' => $import->account ? [
                    'id' => $import->account->id,
                    'name' => $import->account->name,
                ] : null,
                'application' => $import->application ? [
                    'id' => $import->application->id,
                    'name' => $import->application->name,
                ] : null,
            ]);

        return Inertia::render('Settings/MerchantImporter', [
            'imports' => $imports,
        ]);
    }

    /**
     * Process uploaded DocuSign contracts - supports multiple ZIP files
     */
    public function import(): JsonResponse|RedirectResponse
    {
        $validated = Request::validate([
            'files' => ['required', 'array', 'min:1', 'max:100'], // Support up to 100 files
            'files.*' => ['required', 'file', 'mimes:zip', 'max:51200'], // Only ZIP files
        ]);

        $results = [
            'total' => count($validated['files']),
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($validated['files'] as $index => $file) {
            try {
                DB::beginTransaction();
                
                $result = $this->processZipFile($file);
                
                DB::commit();
                $results['successful']++;
                
                Log::info('Successfully imported merchant', [
                    'file_index' => $index,
                    'merchant_name' => $result['merchant_name'],
                    'account_id' => $result['account']->id,
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                $results['failed']++;
                $results['errors'][] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Merchant import failed', [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Create failed import record
                MerchantImport::create([
                    'merchant_name' => $file->getClientOriginalName(),
                    'user_id' => auth()->id(),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        // Build success message
        $message = "Import complete: {$results['successful']} successful";
        if ($results['failed'] > 0) {
            $message .= ", {$results['failed']} failed";
        }

        // Return with flash messages - use 'importErrors' instead of 'errors'
        if ($results['failed'] > 0) {
            return Redirect::back()
                ->with('success', $message)
                ->with('importErrors', $results['errors']); // Use custom key
        }

        return Redirect::back()->with('success', $message);
    }
    
    /**
     * Process a ZIP file containing DocuSign documents
     */
    private function processZipFile($file): array
    {
        $zip = new ZipArchive;
        
        if ($zip->open($file->getPathname()) !== true) {
            throw new \Exception('Failed to open ZIP file: ' . $file->getClientOriginalName());
        }

        $tempDir = storage_path('app/temp/' . uniqid());
        mkdir($tempDir, 0777, true);

        $zip->extractTo($tempDir);
        $zip->close();

        try {
            // Find required files
            $summaryPdf = null;
            $contractPdf = null;
            $formPdf = null;

            $files = scandir($tempDir);
            foreach ($files as $filename) {
                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'pdf') {
                    continue;
                }

                $fullPath = $tempDir . '/' . $filename;
                $lowerFilename = strtolower($filename);
                
                // Identify files by name patterns
                if (str_contains($lowerFilename, 'summary')) {
                    $summaryPdf = $fullPath;
                } elseif (str_contains($lowerFilename, 'contract') || 
                        str_contains($lowerFilename, 'g2pay') ||
                        str_contains($lowerFilename, 'merchant_contract')) {
                    $contractPdf = $fullPath;
                } elseif (str_contains($lowerFilename, 'merchant_pre_vet') ||
                        str_contains($lowerFilename, 'application') ||
                        str_contains($lowerFilename, 'form')) {
                    $formPdf = $fullPath;
                }
            }

            // Summary.pdf is REQUIRED for extracting merchant info
            if (!$summaryPdf) {
                throw new \Exception('Summary.pdf not found in ZIP. This file is required to extract merchant information.');
            }

            // At least one contract document is required
            if (!$contractPdf && !$formPdf) {
                throw new \Exception('No contract or form PDF found in ZIP file.');
            }

            // Extract merchant info from Summary.pdf AND contract PDF
            $merchantInfo = $this->extractMerchantInfoFromSummary($summaryPdf, $contractPdf);

            Log::info('Extracted merchant info from Summary.pdf', $merchantInfo);

            // Check if account exists
            $account = Account::where('email', $merchantInfo['email'])->first();
            
            if (!$account) {
                $plainPassword = Account::generatePassword();
                $account = Account::create([
                    'name' => $merchantInfo['name'],
                    'recipient_name' => $merchantInfo['recipient_name'],
                    'email' => $merchantInfo['email'],
                    'password' => $plainPassword,
                    'user_id' => auth()->id(),
                    'status' => Account::STATUS_CONFIRMED,
                ]);

                event(new \App\Events\AccountCredentialsEvent($account, $plainPassword));
                
                Log::info('Created new account from DocuSign import', [
                    'account_id' => $account->id,
                    'email' => $merchantInfo['email'],
                ]);
            }

            // Create application with extracted fee information
            $application = Application::create([
                'account_id' => $account->id,
                'user_id' => auth()->id(),
                'name' => 'Imported Contract - ' . now()->format('Y-m-d H:i'),
                'trading_name' => $merchantInfo['name'],
                'scaling_fee' => $merchantInfo['fees']['scaling_fee'] ?? 0,
                'transaction_percentage' => $merchantInfo['fees']['transaction_percentage'] ?? 0,
                'transaction_fixed_fee' => $merchantInfo['fees']['transaction_fixed_fee'] ?? 0,
                'monthly_fee' => $merchantInfo['fees']['monthly_fee'] ?? 0,
                'monthly_minimum' => $merchantInfo['fees']['monthly_minimum'] ?? 0,
                'setup_fee' => 0, // Not extracted from contract currently
            ]);

            // Store all PDFs
            if ($contractPdf) {
                $this->storePdfDocument($application, $contractPdf, 'contract.pdf', 'contract');
            }
            if ($formPdf) {
                $this->storePdfDocument($application, $formPdf, 'application_form.pdf', 'application_form');
            }
            
            // Update application status
            $application->status->update([
                'contract_sent_at' => now(),
                'contract_signed_at' => now(),
                'contract_completed_at' => now(),
            ]);

            $application->status->transitionTo('contract_signed', 'Contract imported from DocuSign');

            // Create import record
            MerchantImport::create([
                'merchant_name' => $merchantInfo['name'],
                'account_id' => $account->id,
                'application_id' => $application->id,
                'user_id' => auth()->id(),
                'status' => 'success',
            ]);

            return [
                'merchant_name' => $merchantInfo['name'],
                'account' => $account,
                'application' => $application,
            ];

        } finally {
            // Clean up temp directory
            $this->deleteDirectory($tempDir);
        }
    }
    
    /**
     * Extract merchant information from Summary.pdf (DocuSign completion certificate)
     * AND from the actual contract PDF to get the company name and fee information
     */
    private function extractMerchantInfoFromSummary(string $summaryPath, string $contractPdf = null): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($summaryPath);
        $text = $pdf->getText();

        Log::info('Summary PDF content (first 2000 chars)', [
            'content' => substr($text, 0, 2000),
        ]);

        // Extract SIGNER email from Summary.pdf (person who signed)
        $signerEmail = null;
        $signerName = null;

        Log::info('Starting email extraction from Summary PDF');

        // Method 1: Find email in Signer Events section
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})[\s\n]+Security Level:/i', $text, $matches)) {
            Log::info('Email Method 1: Found email with Security Level pattern', ['email' => $matches[1]]);
            $signerEmail = trim($matches[1]);
            
            // Get the name that appears before this email
            $emailPos = strpos($text, $signerEmail);
            $beforeEmail = substr($text, max(0, $emailPos - 200), 200);
            
            Log::info('Looking for name before email', ['text_before_email' => $beforeEmail]);
            
            if (preg_match('/\n([A-Z][a-zA-Z\s\'-]+)\s*\n/i', $beforeEmail, $nameMatches)) {
                $signerName = trim($nameMatches[1]);
                Log::info('Email Method 1: Found signer name', ['name' => $signerName]);
            } else {
                Log::info('Email Method 1: Could not find signer name before email');
            }
        } else {
            Log::info('Email Method 1: Pattern did not match');
        }

        // Method 2: Alternative pattern
        if (!$signerEmail || !$signerName) {
            Log::info('Email Method 2: Trying alternative pattern', [
                'has_email' => $signerEmail !== null,
                'has_name' => $signerName !== null
            ]);
            
            if (preg_match('/([A-Z][a-zA-Z\s\'-]+)\s+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s+Security Level:/i', $text, $matches)) {
                $signerName = trim($matches[1]);
                $signerEmail = trim($matches[2]);
                Log::info('Email Method 2: Found both name and email', [
                    'name' => $signerName,
                    'email' => $signerEmail
                ]);
            } else {
                Log::info('Email Method 2: Pattern did not match');
            }
        } else {
            Log::info('Email Method 2: Skipped (already have email and name)');
        }

        // Method 3: Find any valid email that's not a system email
        if (!$signerEmail) {
            Log::info('Email Method 3: Looking for any valid email');
            
            if (preg_match_all('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $text, $allEmails)) {
                Log::info('Email Method 3: Found emails', ['count' => count($allEmails[1]), 'emails' => $allEmails[1]]);
                
                foreach ($allEmails[1] as $foundEmail) {
                    if (!str_contains(strtolower($foundEmail), 'docusign') && 
                        !str_contains(strtolower($foundEmail), 'g2pay')) {
                        $signerEmail = $foundEmail;
                        Log::info('Email Method 3: Selected email', ['email' => $signerEmail]);
                        break;
                    } else {
                        Log::info('Email Method 3: Skipped system email', ['email' => $foundEmail]);
                    }
                }
            } else {
                Log::info('Email Method 3: No emails found');
            }
        } else {
            Log::info('Email Method 3: Skipped (already have email)');
        }

        // Method 1b: If we have email but no name, look for name directly before email
        if ($signerEmail && !$signerName) {
            Log::info('Email Method 1b: Looking for name on line directly before email');
            
            // Pattern: Name on one line, email on next line
            if (preg_match('/\n([A-Z][a-zA-Z\s\'-]+)\s*\n\s*' . preg_quote($signerEmail, '/') . '/i', $text, $nameMatches)) {
                $signerName = trim($nameMatches[1]);
                Log::info('Email Method 1b: Found signer name directly before email', ['name' => $signerName]);
            } else {
                Log::info('Email Method 1b: Pattern did not match');
            }
        } else {
            Log::info('Email Method 1b: Skipped', [
                'has_email' => $signerEmail !== null,
                'has_name' => $signerName !== null
            ]);
        }

        Log::info('Email extraction complete', [
            'signer_email' => $signerEmail,
            'signer_name' => $signerName
        ]);

        $recipientName = $signerName;

        // Initialize variables for contract data
        $merchantCompanyName = null;
        $fees = [
            'transaction_fixed_fee' => 0,
            'monthly_fee' => 0,
            'monthly_minimum' => 0,
            'scaling_fee' => 0,
            'transaction_percentage' => 0,
        ];

        // Extract company name and fees from contract PDF (application form - document 2)
        Log::info('Extracting company name and fees from contract PDF');
        
        if ($contractPdf && file_exists($contractPdf)) {
            try {
                $contractPdfParsed = $parser->parseFile($contractPdf);
                $contractText = $contractPdfParsed->getText();
                
                Log::info('Contract PDF content (first 2000 chars)', [
                    'content' => substr($contractText, 0, 2000),
                ]);
                
                // Extract REGISTERED COMPANY NAME (most reliable)
                Log::info('Looking for "REGISTERED COMPANY NAME"');
                if (preg_match('/REGISTERED COMPANY NAME[*\s]*:?[\s\n]+([A-Z][A-Za-z\s&\(\)\.,-Ltd]+?)(?:\n|$)/i', $contractText, $matches)) {
                    $merchantCompanyName = trim($matches[1]);
                    $merchantCompanyName = preg_replace('/\s+/', ' ', $merchantCompanyName);
                    Log::info('Found REGISTERED COMPANY NAME', ['company' => $merchantCompanyName]);
                } else {
                    Log::info('REGISTERED COMPANY NAME not found');
                }
                
                // Extract fees from the contract PDF
                // Based on DocuSignService tabs positioning, these fields are in the contract
                
                // All Request Types - Fixed fee (near "All request types")
                if (preg_match('/All request types[^\n]*[\s\n]+.*?£\s*([0-9]+\.?[0-9]*)/i', $contractText, $matches)) {
                    $fees['transaction_fixed_fee'] = floatval($matches[1]);
                    Log::info('Found transaction_fixed_fee', ['value' => $fees['transaction_fixed_fee']]);
                }
                
                // Monthly Fee (first occurrence - near "Monthly Fee")
                if (preg_match('/Monthly Fee(?:\s*\(inc PCI\))?[^\n]*[\s\n]+.*?£\s*([0-9]+\.?[0-9]*)/i', $contractText, $matches)) {
                    $fees['monthly_fee'] = floatval($matches[1]);
                    Log::info('Found monthly_fee', ['value' => $fees['monthly_fee']]);
                }
                
                // Service fee/monthly minimum
                if (preg_match('/Service fee\/monthly minimum[^\n]*[\s\n]+.*?£\s*([0-9]+\.?[0-9]*)/i', $contractText, $matches)) {
                    $fees['monthly_minimum'] = floatval($matches[1]);
                    Log::info('Found monthly_minimum', ['value' => $fees['monthly_minimum']]);
                    
                    // Check if there's a scaling fee mentioned (e.g., "£X first month. £Y thereafter")
                    if (preg_match('/£\s*[0-9]+\.?[0-9]*\s+first month\.\s*£\s*([0-9]+\.?[0-9]*)\s+thereafter/i', $contractText, $scalingMatches)) {
                        $fees['scaling_fee'] = floatval($scalingMatches[1]);
                        Log::info('Found scaling_fee', ['value' => $fees['scaling_fee']]);
                    }
                }
                
                // UK Consumer Debit/Credit - Percentage
                if (preg_match('/UK Consumer (?:Debit|Credit)[^\n]*[\s\n]+.*?([0-9]+\.?[0-9]*)\s*%/i', $contractText, $matches)) {
                    $fees['transaction_percentage'] = floatval($matches[1]);
                    Log::info('Found transaction_percentage', ['value' => $fees['transaction_percentage']]);
                }
                
                Log::info('Extracted fees from contract', $fees);
                
            } catch (\Exception $e) {
                Log::warning('Could not parse contract PDF for merchant name and fees', [
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Contract PDF not provided or does not exist');
        }

        // Final fallback: Use signer name if no company name found
        if (!$merchantCompanyName && $signerName) {
            Log::warning('Final Fallback: Using signer name as merchant name - this may not be the company name', [
                'signer_name' => $signerName
            ]);
            $merchantCompanyName = $signerName;
        } else if (!$merchantCompanyName) {
            Log::error('Final Fallback: No company name found and no signer name to fall back on');
        }

        Log::info('Extracted merchant info - FINAL RESULT', [
            'merchant_company_name' => $merchantCompanyName,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'fees' => $fees,
        ]);

        if (!$merchantCompanyName || !$signerEmail) {
            throw new \Exception(
                'Could not extract merchant company name and signer email from contract documents. ' .
                'Please ensure the ZIP file contains both Summary.pdf and the signed contract PDF. ' .
                'Found: Company=' . ($merchantCompanyName ?? 'none') . ', Email=' . ($signerEmail ?? 'none')
            );
        }

        return [
            'name' => $merchantCompanyName,  // Company name for account
            'email' => $signerEmail,         // Signer's email for account login
            'recipient_name' => $recipientName,
            'fees' => $fees,                 // Fee information from contract
        ];
    }

    /**
     * Store a PDF document
     */
    private function storePdfDocument(Application $application, string $sourcePath, string $filename, string $category): void
    {
        $destinationPath = 'applications/' . $application->id . '/documents/' . time() . '_' . $filename;
        
        Storage::disk('public')->put(
            $destinationPath,
            file_get_contents($sourcePath)
        );

        ApplicationDocument::create([
            'application_id' => $application->id,
            'document_type' => 'application/pdf',
            'document_category' => $category,
            'file_path' => $destinationPath,
            'original_filename' => $filename,
            'uploaded_by' => auth()->id(),
            'uploaded_by_type' => 'user',
            'external_system' => 'docusign_import',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Recursively delete a directory
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}