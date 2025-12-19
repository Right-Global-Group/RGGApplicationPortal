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

        // FIX 1: IMPROVED SIGNER NAME CLEANING
        if ($signerName) {
            // Store original for logging
            $originalSignerName = $signerName;
            
            // Remove common PDF extraction artifacts
            $signerName = preg_replace('/[\r\n\t]+/', ' ', $signerName); // Remove newlines, tabs
            $signerName = preg_replace('/\s+/', ' ', $signerName); // Normalize spaces
            
            // Remove "Docusign" and everything BEFORE it (more precise)
            $signerName = preg_replace('/^.*?docusign\s+/i', '', $signerName);
            
            // Remove common prefixes
            $signerName = preg_replace('/^(Signed by:?|Signature:?|Name:?|Signer:?)\s*/i', '', $signerName);
            
            // Remove trailing role/status text that might have been captured
            $signerName = preg_replace('/\s+(completed|signed|needs to sign|security level).*$/i', '', $signerName);
            
            // Trim again
            $signerName = trim($signerName);
            
            // FIX 2: VALIDATE THE NAME - don't use if it looks corrupted
            // Name should be 2-4 words, each starting with capital letter
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
                $signerName = null; // Don't use corrupted name
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
                
                // LOG: Show first 1000 characters to see structure
                Log::info('Form PDF first 1000 chars:', [
                    'text' => substr($formText, 0, 1000)
                ]);
                
                // Pattern 1: Look for text after "REGISTERED COMPANY NAME" field
                $fieldPos = stripos($formText, '1. REGISTERED COMPANY NAME');
                Log::info('Field 1 position search:', [
                    'found' => $fieldPos !== false,
                    'position' => $fieldPos
                ]);
                
                if ($fieldPos !== false) {
                    $afterField = substr($formText, $fieldPos, 500);
                    
                    Log::info('Text after field 1 (500 chars):', [
                        'text' => $afterField
                    ]);

                    if (preg_match('/1\.\s*REGISTERED COMPANY NAME\*?\s*[\r\n]+([^\r\n]+(?:[\r\n]+[^\r\n]+)?)/', $afterField, $matches)) {
                        $potentialName = trim($matches[1]);
                        
                        Log::info('Pattern 1 MATCH found:', [
                            'raw_match' => $matches[1],
                            'trimmed' => $potentialName
                        ]);
                        
                        // Clean up: remove newlines within the name and consolidate spaces
                        $potentialName = preg_replace('/[\r\n]+/', ' ', $potentialName);
                        $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                        
                        Log::info('After cleanup:', [
                            'cleaned' => $potentialName
                        ]);
                        
                        // Skip if it's clearly a field label
                        if (!preg_match('/^(2\.|REGISTRATION|NUMBER|ADDRESS|TRADING)/i', $potentialName)) {
                            $merchantCompanyName = $potentialName;
                            Log::info('✓ Pattern 1 SUCCESS - Set as merchantCompanyName:', [
                                'value' => $merchantCompanyName
                            ]);
                        } else {
                            Log::info('✗ Pattern 1 REJECTED - looks like field label:', [
                                'value' => $potentialName
                            ]);
                        }
                    } else {
                        Log::info('✗ Pattern 1 NO MATCH - regex did not match');
                    }
                }
                
                // VALIDATION CHECK
                if ($merchantCompanyName) {
                    Log::info('Validating extracted name for business type keywords:', [
                        'name' => $merchantCompanyName
                    ]);
                    
                    $lowerName = strtolower($merchantCompanyName);
                    // If it contains typical business description words, it's probably from field 5, not field 1
                    if (preg_match('/(prize|competition|products|services|offered|marketed|website|ecom|moto)/i', $merchantCompanyName)) {
                        Log::warning('✗ VALIDATION FAILED - looks like business type, will try Pattern 2', [
                            'extracted' => $merchantCompanyName,
                            'matched_keywords' => true
                        ]);
                        $merchantCompanyName = null; // Clear and try Pattern 2
                    } else {
                        Log::info('✓ VALIDATION PASSED - does not look like business type');
                    }
                }
                
                // Pattern 2: Look at bottom of page after "Docusign Envelope ID:"
                if (!$merchantCompanyName) {
                    Log::info('=== TRYING PATTERN 2 (Bottom of page) ===');
                    
                    // First, extract the text after "Docusign Envelope ID:"
                    $envelopeIdPos = stripos($formText, 'Docusign Envelope ID:');
                    Log::info('Envelope ID position search:', [
                        'found' => $envelopeIdPos !== false,
                        'position' => $envelopeIdPos
                    ]);
                    
                    if ($envelopeIdPos !== false) {
                        $afterEnvelope = substr($formText, $envelopeIdPos);
                        
                        Log::info('Text after Envelope ID (first 500 chars):', [
                            'text' => substr($afterEnvelope, 0, 500)
                        ]);
                        
                        // Split into lines
                        $lines = preg_split('/[\r\n]+/', $afterEnvelope);
                        
                        Log::info('Lines after envelope ID:', [
                            'line_count' => count($lines),
                            'first_10_lines' => array_slice($lines, 0, 10)
                        ]);
                        
                        // The first line will be "Docusign Envelope ID: XXXXX" potentially with text after it
                        // We need to extract any text after the envelope ID on the same line
                        $firstLine = $lines[0] ?? '';
                        
                        // Extract text that comes after the envelope ID on the same line
                        // Envelope ID format: alphanumeric with hyphens, like "199F4FC8-A131-423B-BE8E-6C8020B72766"
                        if (preg_match('/Docusign Envelope ID:\s*([A-F0-9\-]+)\s*(.+)?/i', $firstLine, $match)) {
                            $textAfterEnvelopeId = isset($match[2]) ? trim($match[2]) : '';
                            
                            Log::info('Envelope ID line parsed:', [
                                'envelope_id' => $match[1],
                                'text_after' => $textAfterEnvelopeId
                            ]);
                                                        
                            // Get subsequent lines (skip the first line which is the envelope ID line)
                            $linesAfterEnvelope = [];
                            
                            // If there's text after the envelope ID, add it as the first line
                            if (!empty($textAfterEnvelopeId) && strlen($textAfterEnvelopeId) > 0) {
                                $linesAfterEnvelope[] = $textAfterEnvelopeId;
                            }
                            
                            // Add the rest of the lines
                            for ($i = 1; $i < min(count($lines), 6); $i++) {
                                $line = trim($lines[$i]);
                                if (strlen($line) > 0) {
                                    $linesAfterEnvelope[] = $line;
                                }
                            }
                        } else {
                            // Fallback: just split and skip first line
                            $linesAfterEnvelope = [];
                            for ($i = 1; $i < min(count($lines), 6); $i++) {
                                $line = trim($lines[$i]);
                                if (strlen($line) > 0) {
                                    $linesAfterEnvelope[] = $line;
                                }
                            }
                        }
                        
                        Log::info('Lines to analyze for company name:', [
                            'lines' => $linesAfterEnvelope
                        ]);
                    
                        if (count($linesAfterEnvelope) > 0) {
                            // FIX: Smart company name extraction with multi-line and postcode handling
                            $potentialName = null;
                                                        
                            // Helper function to check if a line is a UK postcode
                            $isPostcodeFn = function($line) {
                                // UK postcode patterns: CR02LA, ME115BE, SM46EE, etc.
                                return preg_match('/^[A-Z]{1,2}\d{1,2}[A-Z]?\s?\d[A-Z]{2}$/i', trim($line));
                            };
                            
                            // Helper function to check if a line looks like part of a company name
                            $looksLikeCompanyName = function($line) {
                                $line = trim($line);
                                // Company name should be 2-50 chars, start with capital, contain letters
                                return strlen($line) >= 2 && 
                                       strlen($line) <= 50 && 
                                       ctype_upper($line[0]) &&
                                       preg_match('/[a-zA-Z]{2,}/', $line);
                            };
                            
                            $firstLine = $linesAfterEnvelope[0];
                            $secondLine = isset($linesAfterEnvelope[1]) ? $linesAfterEnvelope[1] : null;
                            
                            Log::info('Analyzing lines:', [
                                'first_line' => $firstLine,
                                'second_line' => $secondLine,
                                'first_looks_like_company' => $looksLikeCompanyName($firstLine),
                                'first_is_postcode' => $isPostcodeFn($firstLine),
                                'second_looks_like_company' => $secondLine ? $looksLikeCompanyName($secondLine) : null,
                                'second_is_postcode' => $secondLine ? $isPostcodeFn($secondLine) : null,
                            ]);
                            
                            // Strategy: Build company name from consecutive valid lines until we hit a postcode
                            $nameParts = [];
                            
                            // Check first line
                            if ($looksLikeCompanyName($firstLine) && !$isPostcodeFn($firstLine)) {
                                $nameParts[] = $firstLine;
                                
                                // Check if second line is also part of company name (not a postcode)
                                if ($secondLine && $looksLikeCompanyName($secondLine) && !$isPostcodeFn($secondLine)) {
                                    // Second line could be part of name OR could be a separate field
                                    // Check if it's a short word that could be part of multi-line name
                                    if (strlen($secondLine) <= 30) {
                                        // Likely part of the company name (e.g., "Kink" + "Competitions")
                                        $nameParts[] = $secondLine;
                                    }
                                }
                            } elseif ($secondLine && $looksLikeCompanyName($secondLine) && !$isPostcodeFn($secondLine)) {
                                // First line was postcode or invalid, try second line
                                $nameParts[] = $secondLine;
                            }
                            
                            Log::info('Name parts collected:', [
                                'parts' => $nameParts
                            ]);
                            
                            if (count($nameParts) > 0) {
                                $potentialName = trim(implode(' ', $nameParts));
                                
                                Log::info('Potential name built:', [
                                    'name' => $potentialName,
                                    'length' => strlen($potentialName)
                                ]);
                                
                                // Final validation: company name should be 5-100 chars
                                if (strlen($potentialName) >= 5 && strlen($potentialName) <= 100) {
                                    $merchantCompanyName = $potentialName;
                                    Log::info('✓ Pattern 2 SUCCESS - Set as merchantCompanyName:', [
                                        'value' => $merchantCompanyName
                                    ]);
                                } else {
                                    Log::info('✗ Pattern 2 REJECTED - length validation failed');
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

        // FIX 3: IMPROVED FEE EXTRACTION FROM CONTRACT PDF
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
                                        
                    // Extract Schedule 3 section
                    $schedule3Pos = stripos($contractText, 'SCHEDULE 3');
                    if ($schedule3Pos !== false) {
                        $schedule3Section = substr($contractText, $schedule3Pos, 5000);
                        
                        // Get text after envelope ID - this contains the actual values
                        $envelopePos = stripos($schedule3Section, 'Docusign Envelope ID:');
                        if ($envelopePos !== false) {
                            // FIX 4A: Skip the envelope ID line itself to avoid extracting its numbers
                            $afterEnvelopeId = substr($schedule3Section, $envelopePos);
                            
                            // Find the end of the envelope ID line (first newline after "Docusign Envelope ID:")
                            $firstNewline = strpos($afterEnvelopeId, "\n");
                            if ($firstNewline !== false) {
                                $valuesSection = substr($afterEnvelopeId, $firstNewline + 1);
                            } else {
                                $valuesSection = $afterEnvelopeId;
                            }
                                                        
                            // FIX 4B: IMPROVED VALUE EXTRACTION REGEX
                            // More flexible pattern that catches percentages with or without % symbol
                            preg_match_all('/(?:£\s*)?(\d+(?:\.\d+)?)\s*(%|p)?/i', $valuesSection, $allMatches, PREG_SET_ORDER);
                            
                            $pounds = [];
                            $percents = [];
                            $allValues = [];
                            
                            foreach ($allMatches as $match) {
                                $value = floatval($match[1]);
                                $unit = isset($match[2]) ? strtolower(trim($match[2])) : '';
                                
                                // Skip obviously invalid values (envelope IDs, etc)
                                if ($value == 0 || $value > 10000) continue;
                                
                                $allValues[] = ['value' => $value, 'unit' => $unit, 'raw' => $match[0]];
                                
                                // FIX 5: IMPROVED PERCENTAGE DETECTION
                                if ($unit === '%') {
                                    // Explicit percentage - this is the most reliable
                                    $percents[] = $value;
                                } elseif ($unit === 'p') {
                                    // Pence values
                                    $pounds[] = $value < 1 ? $value : $value / 100;
                                } elseif (strpos($match[0], '£') !== false) {
                                    // Has pound symbol, definitely a monetary value
                                    $pounds[] = $value;
                                } elseif ($value > 0 && $value < 10 && $unit === '') {
                                    // Small values without units could be percentages
                                    // But we'll be conservative and only add if there's no % symbol version
                                    // We'll handle this after collecting all values
                                } else {
                                    // Larger values without clear indication
                                    $pounds[] = $value;
                                }
                            }
                            
                            // Reset fees
                            $fees = [
                                'transaction_fixed_fee' => 0,
                                'monthly_fee' => 0,
                                'monthly_minimum' => 0,
                                'scaling_fee' => 0,
                                'transaction_percentage' => 0,
                            ];
                            
                            // FIX 6: Extract transaction_percentage - ONLY use explicit % values
                            // Filter to get only values with explicit % symbol (most reliable)
                            $explicitPercents = [];
                            foreach ($allValues as $val) {
                                if ($val['unit'] === '%') {
                                    $explicitPercents[] = $val['value'];
                                }
                            }
                            
                            if (count($explicitPercents) >= 1) {
                                // Use the FIRST explicit percentage (UK Consumer Debit/Credit rate)
                                $fees['transaction_percentage'] = $explicitPercents[0];
                            } else {
                                Log::warning('No explicit percentage values found with % symbol');
                            }
                            
                            // Sort pounds by value to categorize them
                            $largePounds = array_filter($pounds, function($v) { return $v >= 50; });
                            $mediumPounds = array_filter($pounds, function($v) { return $v >= 10 && $v < 50; });
                            $smallPounds = array_filter($pounds, function($v) { return $v < 10; });
                            
                            // transaction_fixed_fee is the smallest value
                            if (count($smallPounds) > 0) {
                                $fees['transaction_fixed_fee'] = min($smallPounds);
                            }
                            
                            // monthly_minimum is the first large value
                            if (count($largePounds) > 0) {
                                $sortedLarge = $largePounds;
                                sort($sortedLarge);
                                $fees['monthly_minimum'] = $sortedLarge[0];
                            }
                            
                            // monthly_fee is the medium value
                            if (count($mediumPounds) > 0) {
                                $fees['monthly_fee'] = min($mediumPounds);
                            }
                            
                            // Look for "first month...thereafter" pattern
                            if (preg_match('/£(\d+)\s+first\s+month.*?£(\d+)\s+thereafter/i', $schedule3Section, $monthlyPattern)) {
                                $fees['monthly_minimum'] = floatval($monthlyPattern[1]);
                                $fees['scaling_fee'] = floatval($monthlyPattern[2]);
                            }
                            
                            // Alternative: look for standalone "thereafter" for scaling fee
                            if ($fees['scaling_fee'] === 0 && preg_match('/thereafter.*?£(\d+(?:\.\d+)?)/i', $schedule3Section, $scalingMatch)) {
                                $fees['scaling_fee'] = floatval($scalingMatch[1]);
                                Log::info('Found scaling fee', ['value' => $fees['scaling_fee']]);
                            }  
                        }
                    }
                    
                } catch (\Exception $e) {
                    Log::warning('Could not parse contract PDF', ['error' => $e->getMessage()]);
                }
            } else {
                Log::warning('Contract PDF not found', ['expected_path' => $contractPdf]);
            }
        }

        // FIX 7: IMPROVED FALLBACK CHAIN
        if (!$merchantCompanyName) {
            Log::info('No company name found, using fallbacks');
            // Only use signer name if it's valid and properly cleaned
            if ($signerName && $signerName !== null) {
                $merchantCompanyName = $signerName;
                Log::info('Using signer name as fallback', ['name' => $merchantCompanyName]);
            } elseif ($signerEmail) {
                // Last resort: use email domain as company name
                $emailParts = explode('@', $signerEmail);
                $domain = $emailParts[1] ?? 'Unknown';
                $merchantCompanyName = ucwords(str_replace(['.', '-', '_'], ' ', explode('.', $domain)[0]));
                Log::info('Using email domain as fallback', ['name' => $merchantCompanyName]);
            }
        }

        // Validate - only email is required
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

                // COMMENTED OUT TO PREVENT EMAILS DURING IMPORT
                // event(new \App\Events\AccountCredentialsEvent($account, $plainPassword));
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
            
            // Update application status
            $application->status->update([
                'contract_sent_at' => now(),
                'contract_signed_at' => now(),
                'contract_completed_at' => now(),
            ]);

            $application->status->transitionTo('contract_signed', 'Contract imported from DocuSign');

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