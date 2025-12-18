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
            'files' => ['required', 'array', 'min:1', 'max:10'], // Support up to 10 files
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

        if ($results['failed'] > 0) {
            return Redirect::back()
                ->with('success', $message)
                ->with('errors', $results['errors']);
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

            // Create application
            $application = Application::create([
                'account_id' => $account->id,
                'user_id' => auth()->id(),
                'name' => 'Imported Contract - ' . now()->format('Y-m-d H:i'),
                'trading_name' => $merchantInfo['name'],
                'scaling_fee' => 0,
                'transaction_percentage' => 0,
                'transaction_fixed_fee' => 0,
                'monthly_fee' => 0,
                'monthly_minimum' => 0,
                'setup_fee' => 0,
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
 * AND from the actual contract PDF to get the company name
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

    // PRIORITY 1: Extract company name from Summary PDF first (most reliable)
    $merchantCompanyName = null;
    
    Log::info('Starting company name extraction - PRIORITY 1: Summary PDF');

    // Pattern 1: Look for company name after "Director" or job title in Summary
    if ($signerEmail) {
        Log::info('Summary Pattern 1: Looking for company after job title (with email anchor)', [
            'signer_email' => $signerEmail
        ]);
        
        // Try to find the structure: email, then job title, then company name
        if (preg_match('/'.preg_quote($signerEmail, '/').'[\s\n]+(?:Director|Manager|CEO|Owner|Partner|Chief)[\s\n]+([A-Z][A-Za-z\s&\(\)\.,-Ltd]+?)[\s\n]+(?:Security Level|Signed)/i', $text, $matches)) {
            $merchantCompanyName = trim($matches[1]);
            $merchantCompanyName = preg_replace('/\s+/', ' ', $merchantCompanyName);
            Log::info('Summary Pattern 1: MATCH FOUND', ['company' => $merchantCompanyName]);
        } else {
            Log::info('Summary Pattern 1: No match');
        }
    } else {
        Log::info('Summary Pattern 1: Skipped (no signer email)');
    }

    // Pattern 2: Alternative pattern - job title followed by company name
    if (!$merchantCompanyName) {
        Log::info('Summary Pattern 2: Looking for job title followed by company');
        
        if (preg_match('/(?:Director|Manager|CEO|Owner|Partner|Chief)[^\n]*[\s\n]+([A-Z][A-Za-z\s&\(\)\.,-Ltd]+?)[\s\n]+Security Level/i', $text, $matches)) {
            $merchantCompanyName = trim($matches[1]);
            $merchantCompanyName = preg_replace('/\s+/', ' ', $merchantCompanyName);
            Log::info('Summary Pattern 2: MATCH FOUND', ['company' => $merchantCompanyName]);
        } else {
            Log::info('Summary Pattern 2: No match');
        }
    } else {
        Log::info('Summary Pattern 2: Skipped (already found company name)');
    }

    // Pattern 3: Look for lines between email and "Security Level"
    if (!$merchantCompanyName && $signerEmail) {
        Log::info('Summary Pattern 3: Parsing lines between email and Security Level');
        
        $emailPos = strpos($text, $signerEmail);
        $securityPos = strpos($text, 'Security Level', $emailPos);
        
        Log::info('Summary Pattern 3: Position check', [
            'email_pos' => $emailPos,
            'security_pos' => $securityPos
        ]);
        
        if ($emailPos !== false && $securityPos !== false) {
            $between = substr($text, $emailPos + strlen($signerEmail), $securityPos - $emailPos - strlen($signerEmail));
            
            Log::info('Summary Pattern 3: Text between email and Security Level', [
                'text' => $between
            ]);
            
            // Look for lines that look like company names
            $lines = explode("\n", $between);
            Log::info('Summary Pattern 3: Found lines', ['count' => count($lines), 'lines' => $lines]);
            
            foreach ($lines as $index => $line) {
                $line = trim($line);
                Log::info("Summary Pattern 3: Examining line {$index}", ['line' => $line, 'empty' => empty($line)]);
                
                // Skip empty lines and job titles
                if (empty($line)) {
                    Log::info("Summary Pattern 3: Skipping line {$index} (empty)");
                    continue;
                }
                
                if (preg_match('/^(Director|Manager|CEO|Owner|Partner|Chief)$/i', $line)) {
                    Log::info("Summary Pattern 3: Skipping line {$index} (job title)", ['line' => $line]);
                    continue;
                }
                
                // If line looks like a company name
                if (preg_match('/^[A-Z][A-Za-z\s&\(\)\.,-]+(Ltd|Limited|Inc|LLC|LLP|PLC|plc)?\.?$/i', $line)) {
                    $merchantCompanyName = $line;
                    Log::info("Summary Pattern 3: MATCH FOUND on line {$index}", ['company' => $merchantCompanyName]);
                    break;
                } else {
                    Log::info("Summary Pattern 3: Line {$index} does not match company pattern", ['line' => $line]);
                }
            }
            
            if (!$merchantCompanyName) {
                Log::info('Summary Pattern 3: No matching line found');
            }
        } else {
            Log::info('Summary Pattern 3: Could not find positions');
        }
    } else {
        Log::info('Summary Pattern 3: Skipped', [
            'has_company_name' => $merchantCompanyName !== null,
            'has_signer_email' => $signerEmail !== null
        ]);
    }

    // Pattern 4: Try the Summary "Signed for and on behalf of" field
    if (!$merchantCompanyName) {
        Log::info('Summary Pattern 4: Looking for "Signed for and on behalf of"');
        
        if (preg_match('/Signed for and on behalf of\s+([A-Z][^\n]+)/i', $text, $matches)) {
            $merchantCompanyName = trim($matches[1]);
            Log::info('Summary Pattern 4: MATCH FOUND', ['company' => $merchantCompanyName]);
        } else {
            Log::info('Summary Pattern 4: No match');
        }
    } else {
        Log::info('Summary Pattern 4: Skipped (already found company name)');
    }

    // PRIORITY 2: Only if Summary didn't work, try contract PDF
    Log::info('Starting company name extraction - PRIORITY 2: Contract PDF', [
        'has_company_name' => $merchantCompanyName !== null,
        'contract_pdf_exists' => $contractPdf && file_exists($contractPdf)
    ]);
    
    if (!$merchantCompanyName && $contractPdf && file_exists($contractPdf)) {
        try {
            $contractPdfParsed = $parser->parseFile($contractPdf);
            $contractText = $contractPdfParsed->getText();
            
            Log::info('Contract PDF content (first 2000 chars)', [
                'content' => substr($contractText, 0, 2000),
            ]);
            
            // Pattern 1: "REGISTERED COMPANY NAME" field (most reliable in contract)
            Log::info('Contract Pattern 1: Looking for "REGISTERED COMPANY NAME"');
            if (preg_match('/REGISTERED COMPANY NAME[*\s]*:?[\s\n]+([A-Z][A-Za-z\s&\(\)\.,-]+)/i', $contractText, $matches)) {
                $merchantCompanyName = trim($matches[1]);
                Log::info('Contract Pattern 1: MATCH FOUND', ['company' => $merchantCompanyName]);
            } else {
                Log::info('Contract Pattern 1: No match');
            }
            
            // Pattern 2: Look in document metadata/form fields
            if (!$merchantCompanyName) {
                Log::info('Contract Pattern 2: Looking for "Company:" field');
                if (preg_match('/Company[:\s]+([A-Z][A-Za-z\s&\(\)\.,-]+)/i', $contractText, $matches)) {
                    $merchantCompanyName = trim($matches[1]);
                    Log::info('Contract Pattern 2: MATCH FOUND', ['company' => $merchantCompanyName]);
                } else {
                    Log::info('Contract Pattern 2: No match');
                }
            } else {
                Log::info('Contract Pattern 2: Skipped (already found company name)');
            }
            
            // Pattern 3: "hereinafter referred to as" pattern (common in contracts)
            if (!$merchantCompanyName) {
                Log::info('Contract Pattern 3: Looking for "hereinafter referred to as"');
                if (preg_match('/([A-Z][A-Za-z\s&\(\)\.,-]{3,50}?)\s+\(hereinafter referred to as/i', $contractText, $matches)) {
                    $merchantCompanyName = trim($matches[1]);
                    Log::info('Contract Pattern 3: MATCH FOUND', ['company' => $merchantCompanyName]);
                } else {
                    Log::info('Contract Pattern 3: No match');
                }
            } else {
                Log::info('Contract Pattern 3: Skipped (already found company name)');
            }

            // Pattern 4: "incorporated in England and Wales" - LAST RESORT (least reliable)
            if (!$merchantCompanyName) {
                Log::info('Contract Pattern 4: Looking for "incorporated in England and Wales" (LAST RESORT)');
                if (preg_match('/([A-Z][A-Za-z\s&\(\)\.,-]{4,}?)\s+incorporated in England and Wales/i', $contractText, $matches)) {
                    $merchantCompanyName = trim($matches[1]);
                    $merchantCompanyName = preg_replace('/\s+/', ' ', $merchantCompanyName);
                    Log::info('Contract Pattern 4: MATCH FOUND', ['company' => $merchantCompanyName]);
                    Log::warning('Using "incorporated in England and Wales" pattern - this may be unreliable');
                } else {
                    Log::info('Contract Pattern 4: No match');
                }
            } else {
                Log::info('Contract Pattern 4: Skipped (already found company name)');
            }
            
        } catch (\Exception $e) {
            Log::warning('Could not parse contract PDF for merchant name', [
                'error' => $e->getMessage(),
            ]);
        }
    } else {
        Log::info('Skipping contract PDF parsing', [
            'reason' => $merchantCompanyName ? 'Already found company name in Summary' : 'File not provided or does not exist'
        ]);
    }

    // Final fallback: Use signer name if nothing else found (least preferred)
    if (!$merchantCompanyName && $signerName) {
        Log::warning('Final Fallback: Using signer name as merchant name - this may not be the company name', [
            'signer_name' => $signerName
        ]);
        $merchantCompanyName = $signerName;
    } else if (!$merchantCompanyName) {
        Log::error('Final Fallback: No company name found and no signer name to fall back on');
    } else {
        Log::info('Final Fallback: Skipped (already found company name)');
    }

    Log::info('Extracted merchant info - FINAL RESULT', [
        'merchant_company_name' => $merchantCompanyName,
        'signer_name' => $signerName,
        'signer_email' => $signerEmail,
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