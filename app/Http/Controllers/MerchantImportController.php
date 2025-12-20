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
            ->paginate(50)
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
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*' => ['required', 'file', 'mimes:zip', 'max:51200'],
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

                MerchantImport::create([
                    'merchant_name' => $file->getClientOriginalName(),
                    'user_id' => auth()->id(),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        $message = "Import complete: {$results['successful']} successful";
        if ($results['failed'] > 0) {
            $message .= ", {$results['failed']} failed";
        }

        if ($results['failed'] > 0) {
            return Redirect::back()
                ->with('success', $message)
                ->with('importErrors', $results['errors']);
        }

        return Redirect::back()->with('success', $message);
    }
    
    /**
     * Extract DocuSign signature status from Summary.pdf
     * Returns array with: ['is_complete' => bool, 'signature_count' => int, 'has_completed_status' => bool]
     */
    private function extractDocuSignSignatureStatus(string $summaryPath): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($summaryPath);
        $text = $pdf->getText();
        
        Log::info('=== EXTRACTING DOCUSIGN SIGNATURE STATUS ===');
        
        // Extract "Signatures: X" count
        $signatureCount = 0;
        if (preg_match('/Signatures:\s*(\d+)/i', $text, $match)) {
            $signatureCount = (int)$match[1];
            Log::info('Found Signatures field', ['count' => $signatureCount]);
        }
        
        // Check if "Signature" column has "Completed" status
        $hasCompletedStatus = false;
        if (preg_match('/Signature\s+Timestamp.*?Completed/is', $text, $match)) {
            $hasCompletedStatus = true;
            Log::info('Found "Completed" in Signature column');
        }
        
        // Contract is fully signed if EITHER:
        // 1. Signature count >= 2, OR
        // 2. Signature column shows "Completed"
        $isComplete = ($signatureCount >= 2) || $hasCompletedStatus;
        
        Log::info('Signature status analysis', [
            'signature_count' => $signatureCount,
            'has_completed_status' => $hasCompletedStatus,
            'is_complete' => $isComplete,
        ]);
        
        return [
            'is_complete' => $isComplete,
            'signature_count' => $signatureCount,
            'has_completed_status' => $hasCompletedStatus,
        ];
    }
    
    /**
     * Extract DocuSign envelope ID from Summary.pdf
     */
    private function extractEnvelopeId(string $summaryPath): ?string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($summaryPath);
        $text = $pdf->getText();
        
        // Pattern: Envelope Id: XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
        if (preg_match('/Envelope\s+Id:\s*([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/i', $text, $match)) {
            $envelopeId = $match[1];
            Log::info('Extracted envelope ID from summary', ['envelope_id' => $envelopeId]);
            return $envelopeId;
        }
        
        Log::warning('Could not extract envelope ID from summary PDF');
        return null;
    }
    
    /**
     * Extract merchant information from Summary.pdf and application form PDF
     */
    private function extractMerchantInfoFromSummary(string $summaryPath, string $formPdf = null): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($summaryPath);
        $text = $pdf->getText();

        // Extract SIGNER email and name from Summary.pdf
        $signerEmail = null;
        $signerName = null;
        $originalSignerName = null;

        // Method 1: Find the SECOND person in the signer events (skip G2Pay Admin)
        if (preg_match_all('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)\s+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s+([A-Z][a-zA-Z\s]+)\s+([A-Za-z][A-Za-z0-9\s&\(\)\.,\'\-]+)\s+Security Level:/s', $text, $allMatches, PREG_SET_ORDER)) {
                        
            foreach ($allMatches as $index => $match) {
                $potentialEmail = trim($match[2]);
                
                if (stripos($potentialEmail, 'g2pay') !== false || stripos($potentialEmail, 'management@') !== false) {
                    continue;
                }
                
                $originalSignerName = trim($match[1]);
                $signerName = $originalSignerName;
                $signerEmail = $potentialEmail;
                
                break;
            }
        }

        // Method 2: If Method 1 didn't work, look for non-G2Pay email and try to extract name
        if (!$signerEmail || !$signerName) {
            if (preg_match('/([A-Z][a-z]+\s+[A-Z][a-z]+)\s*[\r\n]+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s*[\r\n]+Security Level:/i', $text, $match)) {
                $potentialName = trim($match[1]);
                $potentialEmail = trim($match[2]);
                
                if (stripos($potentialEmail, 'g2pay') === false && stripos($potentialEmail, 'management@') === false && stripos($potentialEmail, 'docusign') === false) {
                    $signerName = $potentialName;
                    $signerEmail = $potentialEmail;
                }
            }
            
            // Fallback: just find email
            if (!$signerEmail) {
                if (preg_match_all('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $text, $emailMatches)) {
                    foreach ($emailMatches[1] as $foundEmail) {
                        if (stripos($foundEmail, 'docusign') !== false || stripos($foundEmail, 'g2pay') !== false || stripos($foundEmail, 'management@') !== false) {
                            continue;
                        }
                        
                        $signerEmail = trim($foundEmail);
                        
                        // Try to extract name from email if we don't have one
                        if (!$signerName) {
                            $emailLocal = explode('@', $signerEmail)[0];
                            
                            // Pattern 1: firstname.lastname (e.g., brad.passey)
                            if (strpos($emailLocal, '.') !== false) {
                                $parts = explode('.', $emailLocal);
                                $signerName = ucfirst($parts[0]) . ' ' . ucfirst($parts[1]);
                            }
                            // Pattern 2: firstnamenumberslastname (e.g., brad15singleton, sambishop23)
                            elseif (preg_match('/^([a-z]+)\d*([a-z]+)\d*$/i', $emailLocal, $parts)) {
                                if (isset($parts[2]) && strlen($parts[2]) > 2) {
                                    $signerName = ucfirst($parts[1]) . ' ' . ucfirst($parts[2]);
                                }
                            }
                        }
                        
                        break;
                    }
                }
            }
        }

        // Clean signer name
        if ($signerName) {
            $originalSignerName = $signerName;
            $signerName = preg_replace('/[\r\n\t]+/', ' ', $signerName);
            $signerName = preg_replace('/\s+/', ' ', $signerName);
            $signerName = preg_replace('/^.*?docusign\s+/i', '', $signerName);
            $signerName = preg_replace('/^(Signed by:?|Signature:?|Name:?|Signer:?)\s*/i', '', $signerName);
            $signerName = preg_replace('/\s+(completed|signed|needs to sign|security level).*$/i', '', $signerName);
            $signerName = trim($signerName);
            
            $nameParts = explode(' ', $signerName);
            $validName = true;
            
            if (count($nameParts) < 2 || count($nameParts) > 4) {
                $validName = false;
            } else {
                foreach ($nameParts as $part) {
                    if (strlen($part) < 2 || !ctype_upper($part[0])) {
                        $validName = false;
                        break;
                    }
                }
            }
            
            if (!$validName) {
                Log::warning('Signer name appears corrupted, will not use as fallback', [
                    'original' => $originalSignerName,
                    'cleaned' => $signerName
                ]);
                $signerName = null;
            }
        }

        // Initialize variables
        $merchantCompanyName = null;
        $fees = [
            'transaction_fixed_fee' => 0,
            'monthly_fee' => 0,
            'monthly_minimum' => 0,
            'scaling_fee' => 0,
            'transaction_percentage' => 0,
        ];

        // Extract company name from form PDF
        if ($formPdf && file_exists($formPdf)) {
            try {
                $formPdfParsed = $parser->parseFile($formPdf);
                $formText = $formPdfParsed->getText();
                
                Log::info('=== STARTING COMPANY NAME EXTRACTION ===');
                
                $fieldPos = stripos($formText, '1. REGISTERED COMPANY NAME');
                Log::info('Field 1 position search:', [
                    'found' => $fieldPos !== false,
                    'position' => $fieldPos
                ]);
                
                if ($fieldPos !== false) {
                    $afterField = substr($formText, $fieldPos, 500);

                    if (preg_match('/1\.\s*REGISTERED COMPANY NAME\*?\s*[\r\n]+([^\r\n]+(?:[\r\n]+[^\r\n]+)?)/', $afterField, $matches)) {
                        $potentialName = trim($matches[1]);
                        $potentialName = preg_replace('/[\r\n]+/', ' ', $potentialName);
                        $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                        
                        if (!preg_match('/^(2\.|REGISTRATION|NUMBER|ADDRESS|TRADING)/i', $potentialName)) {
                            $merchantCompanyName = $potentialName;
                            Log::info('✓ Pattern 1 SUCCESS - Set as merchantCompanyName:', [
                                'value' => $merchantCompanyName
                            ]);
                        }
                    }
                }
                
                if ($merchantCompanyName) {
                    if (preg_match('/(prize|competition|products|services|offered|marketed|website|ecom|moto)/i', $merchantCompanyName)) {
                        Log::warning('✗ VALIDATION FAILED - looks like business type, will try Pattern 2', [
                            'extracted' => $merchantCompanyName,
                        ]);
                        $merchantCompanyName = null;
                    }
                }
                
                // Pattern 2: Look at bottom of page after "Docusign Envelope ID:"
                if (!$merchantCompanyName) {
                    $envelopeIdPos = stripos($formText, 'Docusign Envelope ID:');
                    
                    if ($envelopeIdPos !== false) {
                        $afterEnvelope = substr($formText, $envelopeIdPos);
                        $lines = preg_split('/[\r\n]+/', $afterEnvelope);
                        
                        $firstLine = $lines[0] ?? '';
                        
                        if (preg_match('/Docusign Envelope ID:\s*([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})(.*)$/i', $firstLine, $match)) {
                            $textAfterEnvelopeId = isset($match[2]) ? trim($match[2]) : '';
                            
                            $linesAfterEnvelope = [];
                            
                            $isBusinessType = false;
                            if (!empty($textAfterEnvelopeId)) {
                                if (preg_match('/(prize|competition|products|services|offered|marketed|website|ecom|moto)/i', $textAfterEnvelopeId)) {
                                    $isBusinessType = true;
                                }
                            }
                            
                            if (!$isBusinessType && !empty($textAfterEnvelopeId) && strlen($textAfterEnvelopeId) > 0) {
                                $linesAfterEnvelope[] = $textAfterEnvelopeId;
                            }
                            
                            for ($i = 1; $i < min(count($lines), 10); $i++) {
                                $line = trim($lines[$i]);
                                if (strlen($line) > 0) {
                                    $linesAfterEnvelope[] = $line;
                                }
                            }
                        } else {
                            $linesAfterEnvelope = [];
                            for ($i = 1; $i < min(count($lines), 6); $i++) {
                                $line = trim($lines[$i]);
                                if (strlen($line) > 0) {
                                    $linesAfterEnvelope[] = $line;
                                }
                            }
                        }
                    
                        if (count($linesAfterEnvelope) > 0) {
                            $potentialName = null;
                                                        
                            $isPostcodeFn = function($line) {
                                return preg_match('/^[A-Z]{1,2}\d{1,2}[A-Z]?\s?\d[A-Z]{2}$/i', trim($line));
                            };
                            
                            $looksLikeCompanyName = function($line) {
                                $line = trim($line);
                                return strlen($line) >= 2 && 
                                       strlen($line) <= 50 && 
                                       ctype_upper($line[0]) &&
                                       preg_match('/[a-zA-Z]{2,}/', $line);
                            };
                            
                            $firstLine = $linesAfterEnvelope[0];
                            $secondLine = isset($linesAfterEnvelope[1]) ? $linesAfterEnvelope[1] : null;
                            
                            $nameParts = [];
                            
                            if ($looksLikeCompanyName($firstLine) && !$isPostcodeFn($firstLine)) {
                                $nameParts[] = $firstLine;
                                
                                if ($secondLine && $looksLikeCompanyName($secondLine) && !$isPostcodeFn($secondLine)) {
                                    if (strlen($secondLine) <= 30) {
                                        $nameParts[] = $secondLine;
                                    }
                                }
                            } elseif ($secondLine && $looksLikeCompanyName($secondLine) && !$isPostcodeFn($secondLine)) {
                                $nameParts[] = $secondLine;
                            }
                            
                            if (count($nameParts) > 0) {
                                $potentialName = trim(implode(' ', $nameParts));
                                
                                if (strlen($potentialName) >= 5 && strlen($potentialName) <= 100) {
                                    $merchantCompanyName = $potentialName;
                                    Log::info('✓ Pattern 2 SUCCESS - Set as merchantCompanyName:', [
                                        'value' => $merchantCompanyName
                                    ]);
                                }
                            }
                        }
                    }
                }
                
                Log::info('=== COMPANY NAME EXTRACTION COMPLETE ===', [
                    'final_result' => $merchantCompanyName
                ]);
                
            } catch (\Exception $e) {
                Log::warning('Could not parse form PDF', ['error' => $e->getMessage()]);
            }
        }

        // Extract fees from contract PDF
        if ($formPdf) {
            $contractPdf = dirname($formPdf) . '/G2PAY_merchant_contract_.docx.pdf';
                        
            if (!file_exists($contractPdf)) {
                $files = scandir(dirname($formPdf));
                
                foreach ($files as $file) {
                    if (stripos($file, 'g2pay') !== false && stripos($file, 'contract') !== false) {
                        $contractPdf = dirname($formPdf) . '/' . $file;
                        break;
                    }
                }
            }
            
            if (file_exists($contractPdf)) {
                try {
                    $contractPdfParsed = $parser->parseFile($contractPdf);
                    $contractText = $contractPdfParsed->getText();
                                        
                    $schedule3Pos = stripos($contractText, 'SCHEDULE 3');
                    if ($schedule3Pos !== false) {
                        $schedule3Section = substr($contractText, $schedule3Pos, 5000);
                        
                        $envelopePos = stripos($schedule3Section, 'Docusign Envelope ID:');
                        if ($envelopePos !== false) {
                            $afterEnvelopeId = substr($schedule3Section, $envelopePos);
                            
                            $firstNewline = strpos($afterEnvelopeId, "\n");
                            if ($firstNewline !== false) {
                                $valuesSection = substr($afterEnvelopeId, $firstNewline + 1);
                            } else {
                                $valuesSection = $afterEnvelopeId;
                            }
                                                        
                            preg_match_all('/(?:£\s*)?(\d+(?:\.\d+)?)\s*(%|p)?/i', $valuesSection, $allMatches, PREG_SET_ORDER);
                            
                            $pounds = [];
                            $percents = [];
                            $allValues = [];
                            
                            foreach ($allMatches as $match) {
                                $value = floatval($match[1]);
                                $unit = isset($match[2]) ? strtolower(trim($match[2])) : '';
                                
                                if ($value == 0 || $value > 10000) continue;
                                
                                $allValues[] = ['value' => $value, 'unit' => $unit, 'raw' => $match[0]];
                                
                                if ($unit === '%') {
                                    $percents[] = $value;
                                } elseif ($unit === 'p') {
                                    $pounds[] = $value < 1 ? $value : $value / 100;
                                } elseif (strpos($match[0], '£') !== false) {
                                    $pounds[] = $value;
                                } else {
                                    $pounds[] = $value;
                                }
                            }
                            
                            $fees = [
                                'transaction_fixed_fee' => 0,
                                'monthly_fee' => 0,
                                'monthly_minimum' => 0,
                                'scaling_fee' => 0,
                                'transaction_percentage' => 0,
                            ];
                            
                            $explicitPercents = [];
                            foreach ($allValues as $val) {
                                if ($val['unit'] === '%') {
                                    $explicitPercents[] = $val['value'];
                                }
                            }
                            
                            if (count($explicitPercents) >= 1) {
                                $fees['transaction_percentage'] = $explicitPercents[0];
                            }
                            
                            $largePounds = array_filter($pounds, function($v) { return $v >= 50; });
                            $mediumPounds = array_filter($pounds, function($v) { return $v >= 10 && $v < 50; });
                            $smallPounds = array_filter($pounds, function($v) { return $v < 10; });
                            
                            if (count($smallPounds) > 0) {
                                $fees['transaction_fixed_fee'] = min($smallPounds);
                            }
                            
                            if (count($largePounds) > 0) {
                                $sortedLarge = $largePounds;
                                sort($sortedLarge);
                                $fees['monthly_minimum'] = $sortedLarge[0];
                            }
                            
                            if (count($mediumPounds) > 0) {
                                $fees['monthly_fee'] = min($mediumPounds);
                            }
                            
                            if (preg_match('/£(\d+)\s+first\s+month.*?£(\d+)\s+thereafter/i', $schedule3Section, $monthlyPattern)) {
                                $fees['monthly_minimum'] = floatval($monthlyPattern[1]);
                                $fees['scaling_fee'] = floatval($monthlyPattern[2]);
                            }
                            
                            if ($fees['scaling_fee'] === 0 && preg_match('/thereafter.*?£(\d+(?:\.\d+)?)/i', $schedule3Section, $scalingMatch)) {
                                $fees['scaling_fee'] = floatval($scalingMatch[1]);
                            }  
                        }
                    }
                    
                } catch (\Exception $e) {
                    Log::warning('Could not parse contract PDF', ['error' => $e->getMessage()]);
                }
            }
        }

        if (!$merchantCompanyName) {
            if ($signerName && $signerName !== null) {
                $merchantCompanyName = $signerName;
            } elseif ($signerEmail) {
                $emailParts = explode('@', $signerEmail);
                $domain = $emailParts[1] ?? 'Unknown';
                $merchantCompanyName = ucwords(str_replace(['.', '-', '_'], ' ', explode('.', $domain)[0]));
            }
        }

        if (!$signerEmail) {
            throw new \Exception(
                'Could not extract required merchant information. ' .
                'Found: Company=' . ($merchantCompanyName ?? 'none') . 
                ', Signer=' . ($signerName ?? 'none') . 
                ', Email=' . ($signerEmail ?? 'none')
            );
        }

        return [
            'company_name' => $merchantCompanyName ?? 'Unknown Company',
            'signer_name' => $signerName,
            'email' => $signerEmail,
            'fees' => $fees,
        ];
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

            if (!$summaryPdf) {
                throw new \Exception('Summary.pdf not found in ZIP.');
            }

            if (!$contractPdf && !$formPdf) {
                throw new \Exception('No contract or form PDF found in ZIP file.');
            }

            // Extract DocuSign signature status and envelope ID
            $signatureStatus = $this->extractDocuSignSignatureStatus($summaryPdf);
            $envelopeId = $this->extractEnvelopeId($summaryPdf);

            // Extract merchant info
            $merchantInfo = $this->extractMerchantInfoFromSummary($summaryPdf, $formPdf);

            // Check if account exists
            $account = Account::where('email', $merchantInfo['email'])->first();
            
            if (!$account) {
                $plainPassword = Account::generatePassword();
                $account = Account::create([
                    'name' => $merchantInfo['company_name'],
                    'recipient_name' => $merchantInfo['signer_name'] ?? $merchantInfo['company_name'],
                    'email' => $merchantInfo['email'],
                    'password' => $plainPassword,
                    'user_id' => auth()->id(),
                    'status' => Account::STATUS_CONFIRMED,
                ]);
            }

            // Create application with extracted fees
            $application = Application::create([
                'account_id' => $account->id,
                'user_id' => auth()->id(),
                'name' => 'Imported Contract - ' . now()->format('Y-m-d H:i'),
                'trading_name' => $merchantInfo['company_name'],
                'scaling_fee' => $merchantInfo['fees']['scaling_fee'] ?? 0,
                'transaction_percentage' => $merchantInfo['fees']['transaction_percentage'] ?? 0,
                'transaction_fixed_fee' => $merchantInfo['fees']['transaction_fixed_fee'] ?? 0,
                'monthly_fee' => $merchantInfo['fees']['monthly_fee'] ?? 0,
                'monthly_minimum' => $merchantInfo['fees']['monthly_minimum'] ?? 0,
                'setup_fee' => 0,
            ]);

            // Store PDFs
            if ($contractPdf) {
                $this->storePdfDocument($application, $contractPdf, 'contract.pdf', 'contract');
            }
            if ($formPdf) {
                $this->storePdfDocument($application, $formPdf, 'application_form.pdf', 'application_form');
            }
            
            // Update application status based on signature status
            $statusUpdate = [
                'contract_sent_at' => now(),
            ];
            
            // Store envelope ID if we have one
            if ($envelopeId) {
                $statusUpdate['docusign_envelope_id'] = $envelopeId;
                Log::info('Storing envelope ID from import', [
                    'application_id' => $application->id,
                    'envelope_id' => $envelopeId,
                ]);
            }
            
            // Only mark as signed if actually complete
            if ($signatureStatus['is_complete']) {
                $statusUpdate['contract_signed_at'] = now();
                $statusUpdate['contract_completed_at'] = now();
                
                $application->status->update($statusUpdate);
                $application->status->transitionTo('contract_signed', 'Contract imported from DocuSign - fully signed');
                
                Log::info('Import: Contract marked as FULLY SIGNED', [
                    'application_id' => $application->id,
                    'signature_count' => $signatureStatus['signature_count'],
                    'has_completed_status' => $signatureStatus['has_completed_status'],
                ]);
            } else {
                // Contract sent but NOT fully signed - store envelope ID for later use
                $application->status->update($statusUpdate);
                $application->status->transitionTo('contract_sent', 'Contract imported from DocuSign - awaiting signatures');
                
                Log::info('Import: Contract marked as SENT (not fully signed)', [
                    'application_id' => $application->id,
                    'signature_count' => $signatureStatus['signature_count'],
                    'has_completed_status' => $signatureStatus['has_completed_status'],
                    'envelope_id' => $envelopeId,
                ]);
            }

            // Create import record
            MerchantImport::create([
                'merchant_name' => $merchantInfo['company_name'],
                'account_id' => $account->id,
                'application_id' => $application->id,
                'user_id' => auth()->id(),
                'status' => 'success',
            ]);

            return [
                'merchant_name' => $merchantInfo['company_name'],
                'account' => $account,
                'application' => $application,
            ];

        } finally {
            $this->deleteDirectory($tempDir);
        }
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