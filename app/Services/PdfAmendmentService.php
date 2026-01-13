<?php

namespace App\Services;

use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\Log;

class PdfAmendmentService
{
    /**
     * Create an amendment document showing the changes made to a PDF
     * This preserves the original signed document and creates a separate amendment
     * 
     * @param Application $application
     * @param array $fieldValues Changed field values
     * @param array $originalValues Original field values
     * @param string $documentCategory
     * @return string Path to the amendment PDF
     */
    public function createAmendmentPdf(
        Application $application,
        array $fieldValues,
        array $originalValues,
        string $documentCategory
    ): string {
        $changes = [];
        
        foreach ($fieldValues as $fieldName => $newValue) {
            $oldValue = $originalValues[$fieldName] ?? 'N/A';
            
            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $this->formatFieldName($fieldName),
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ];
            }
        }
        
        $data = [
            'application' => $application,
            'changes' => $changes,
            'document_type' => $documentCategory === 'contract' ? 'Contract' : 'Application Form',
            'amended_at' => now(),
            'amended_by' => auth()->user()->name,
        ];
        
        // Generate amendment PDF
        $pdf = DomPdf::loadView('pdfs.amendment', $data);
        
        $tempPath = storage_path('app/temp/amendment_' . time() . '.pdf');
        $pdf->save($tempPath);
        
        Log::info('Amendment PDF created', [
            'application_id' => $application->id,
            'changes_count' => count($changes),
        ]);
        
        return $tempPath;
    }
    
    /**
     * Create a fully updated version of the document with new values
     * This generates a brand new PDF from scratch with the updated data
     */
    public function createUpdatedDocument(
        Application $application,
        array $fieldValues,
        string $documentCategory
    ): string {
        // Merge updated values with application data
        $data = [
            'application' => $application,
            'updated_values' => $fieldValues,
            'generated_at' => now(),
        ];
        
        // Select the appropriate template
        $template = $documentCategory === 'contract' 
            ? 'pdfs.contract-updated' 
            : 'pdfs.application-form-updated';
        
        $pdf = DomPdf::loadView($template, $data);
        
        $tempPath = storage_path('app/temp/updated_' . time() . '.pdf');
        $pdf->save($tempPath);
        
        Log::info('Updated document PDF created', [
            'application_id' => $application->id,
            'category' => $documentCategory,
        ]);
        
        return $tempPath;
    }
    
    private function formatFieldName(string $fieldName): string
    {
        return ucwords(str_replace('_', ' ', $fieldName));
    }
}