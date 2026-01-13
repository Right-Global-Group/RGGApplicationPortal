<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfEditorService
{
    /**
     * Edit a PDF by overlaying new text on specified fields
     * 
     * @param string $pdfPath Full path to the PDF file
     * @param array $fieldValues Array of field_name => new_value
     * @param string $documentCategory 'contract' or 'application_form'
     * @return string Path to the edited PDF
     */
    public function editPdf(string $pdfPath, array $fieldValues, string $documentCategory): string
    {
        try {
            $pdf = new Fpdi();
            $pdf->setSourceFile($pdfPath);
            
            // Import the first page
            $templateId = $pdf->importPage(1);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);
            
            // Get field positions based on document type
            $fieldPositions = $this->getFieldPositions($documentCategory);
            
            // Overlay white rectangles and new text for each edited field
            foreach ($fieldValues as $fieldName => $newValue) {
                if (!isset($fieldPositions[$fieldName])) {
                    continue;
                }
                
                $pos = $fieldPositions[$fieldName];
                
                // Draw white rectangle to cover old text
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($pos['x'], $pos['y'], $pos['width'], $pos['height'], 'F');
                
                // Write new text
                $pdf->SetFont('Arial', '', $pos['font_size'] ?? 10);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY($pos['x'], $pos['y']);
                $pdf->Write(0, $newValue);
            }
            
            // Save to temporary file
            $tempPath = storage_path('app/temp/edited_' . time() . '.pdf');
            $pdf->Output('F', $tempPath);
            
            Log::info('PDF edited successfully', [
                'original' => $pdfPath,
                'output' => $tempPath,
                'fields_edited' => count($fieldValues),
            ]);
            
            return $tempPath;
            
        } catch (\Exception $e) {
            Log::error('Failed to edit PDF', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception('Failed to edit PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Get field positions for overlaying text
     * These coordinates are in FPDF units (mm from top-left)
     * 
     * You'll need to calibrate these based on your actual PDFs
     */
    private function getFieldPositions(string $documentCategory): array
    {
        if ($documentCategory === 'contract') {
            return [
                // Contract page 1 - fee table area (example coordinates)
                'merchant_name' => [
                    'x' => 20,
                    'y' => 40,
                    'width' => 80,
                    'height' => 6,
                    'font_size' => 10,
                ],
                'transaction_fixed_fee' => [
                    'x' => 150,
                    'y' => 100,
                    'width' => 40,
                    'height' => 6,
                    'font_size' => 9,
                ],
                'monthly_minimum' => [
                    'x' => 150,
                    'y' => 110,
                    'width' => 60,
                    'height' => 6,
                    'font_size' => 8,
                ],
                'monthly_fee' => [
                    'x' => 150,
                    'y' => 120,
                    'width' => 40,
                    'height' => 6,
                    'font_size' => 9,
                ],
                'transaction_percentage' => [
                    'x' => 150,
                    'y' => 130,
                    'width' => 40,
                    'height' => 6,
                    'font_size' => 9,
                ],
            ];
        } else {
            // Application form coordinates
            return [
                'registered_company_name' => [
                    'x' => 60,
                    'y' => 30,
                    'width' => 100,
                    'height' => 6,
                    'font_size' => 10,
                ],
                'trading_name' => [
                    'x' => 60,
                    'y' => 40,
                    'width' => 100,
                    'height' => 6,
                    'font_size' => 10,
                ],
                'registration_number' => [
                    'x' => 60,
                    'y' => 50,
                    'width' => 100,
                    'height' => 6,
                    'font_size' => 10,
                ],
                // Add more as needed
            ];
        }
    }
}