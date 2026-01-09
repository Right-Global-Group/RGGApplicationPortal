<?php

namespace App\Http\Controllers;

use App\Events\AllDocumentsUploadedEvent;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationAdditionalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class ApplicationDocumentsController extends Controller
{
    public function store(Application $application): RedirectResponse
    {
        // Check permissions
        $isAccount = auth()->guard('account')->check();
        $uploadedByType = null;
        $uploadedBy = null;

        if ($isAccount) {
            if ($application->account_id !== auth()->guard('account')->id()) {
                abort(403, 'You can only upload documents to your own applications.');
            }
            $uploadedByType = 'account';
            $uploadedBy = auth()->guard('account')->id();
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
                abort(403, 'You can only upload documents to applications you manage.');
            }
            $uploadedByType = 'user';
            $uploadedBy = auth()->guard('web')->id();
        } else {
            abort(403);
        }

        $validated = Request::validate([
            'document_category' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xlsx,xls,csv,jpg,jpeg,png', 'max:102400'],
        ]);
    
        // Validate category is valid for this application
        $validCategories = ApplicationDocument::getAllValidCategoriesForApplication($application);
        if (!in_array($validated['document_category'], $validCategories)) {
            return Redirect::back()->withErrors(['document_category' => 'Invalid document category.']);
        }
    
        $file = Request::file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('applications/' . $application->id . '/documents', $filename, 'public');
    
        // Create document record
        $document = ApplicationDocument::create([
            'application_id' => $application->id,
            'document_type' => $file->getClientMimeType(),
            'document_category' => $validated['document_category'],
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by' => $uploadedBy,
            'uploaded_by_type' => $uploadedByType,
            'status' => 'uploaded',
        ]);
    
        Log::info('Document uploaded', [
            'application_id' => $application->id,
            'category' => $validated['document_category'],
            'filename' => $file->getClientOriginalName(),
        ]);
    
        // Check if this is an additional document and mark it as uploaded
        if (str_starts_with($validated['document_category'], 'additional_requested_')) {
            $docId = str_replace('additional_requested_', '', $validated['document_category']);
            $additionalDoc = $application->additionalDocuments()->find($docId);
            
            if ($additionalDoc) {
                $additionalDoc->update([
                    'is_uploaded' => true,
                    'uploaded_at' => now(),
                ]);
                
                Log::info('Additional document marked as uploaded', [
                    'additional_doc_id' => $additionalDoc->id,
                    'document_name' => $additionalDoc->document_name,
                ]);
            }
        }
    
        // Refresh the application to get updated relationships
        $application->load('documents', 'additionalDocuments');
        
        // Check if all required documents are now uploaded
        Log::info('Checking if all documents uploaded', [
            'application_id' => $application->id,
            'current_step' => $application->status->current_step,
        ]);
        
        if ($application->status->hasAllRequiredDocuments()) {
            Log::info('All documents uploaded - checking if should transition', [
                'current_step' => $application->status->current_step,
                'documents_uploaded_at' => $application->status->documents_uploaded_at,
            ]);
            
            // Set the documents_uploaded timestamp if not already set
            if (!$application->status->documents_uploaded_at) {
                $application->status->update([
                    'documents_uploaded_at' => now()
                ]);
                
                Log::info('Set documents_uploaded_at timestamp');
            }

            // Fire "all documents uploaded" event (ONE email to user)
            event(new AllDocumentsUploadedEvent($application));
            
            // Only transition current_step AND fire event if we're still in early stages
            if (in_array($application->status->current_step, ['created', 'contract_sent'])) {
                Log::info('Transitioning to documents_uploaded and firing event');
                
                $application->status->transitionTo('documents_uploaded', 'All required documents uploaded');
            } else {
                Log::info('All documents uploaded but already past that step - no transition or event', [
                    'current_step' => $application->status->current_step
                ]);
            }
        }
    
        return Redirect::back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Get PDF form fields for editing
     * Returns simple text-based fields that work with any PDF
     */
    public function getPdfFields(Application $application, ApplicationDocument $document): JsonResponse
    {
        $this->checkDocumentAccess($application, $document);

        if (!in_array($document->document_category, ['contract', 'application_form'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only contract and application_form documents can be edited',
            ], 400);
        }

        if ($document->isDumped()) {
            return response()->json([
                'success' => false,
                'message' => 'This document has been removed',
            ], 410);
        }

        try {
            // Return predefined editable fields based on your contract structure
            $fields = $this->getEditableFields($application);

            return response()->json([
                'success' => true,
                'fields' => [1 => $fields], // Page 1
                'page_count' => 1,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get PDF fields', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load fields: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save edited PDF by creating annotations or overlay
     */
    public function savePdfEdits(Application $application, ApplicationDocument $document): JsonResponse
    {
        if (auth()->guard('account')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Accounts cannot edit documents',
            ], 403);
        }

        $user = auth()->guard('web')->user();
        if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit documents for applications you manage',
            ], 403);
        }

        if (!in_array($document->document_category, ['contract', 'application_form'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only contract and application_form documents can be edited',
            ], 400);
        }

        $validated = Request::validate([
            'field_values' => ['required', 'array'],
        ]);

        try {
            $originalPath = storage_path('app/public/' . $document->file_path);
            
            if (!file_exists($originalPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Original document not found',
                ], 404);
            }

            // For now, just copy the original and store the field values in database
            // The edited values will be stored in a JSON field
            $editedFilename = time() . '_edited_' . $document->original_filename;
            $editedPath = 'applications/' . $application->id . '/documents/' . $editedFilename;
            $editedFullPath = storage_path('app/public/' . $editedPath);

            // Copy original file
            copy($originalPath, $editedFullPath);

            // Create new document record with edited field values stored
            $editedDocument = ApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => 'application/pdf',
                'document_category' => $document->document_category,
                'file_path' => $editedPath,
                'original_filename' => 'Edited_' . $document->original_filename,
                'uploaded_by' => $user->id,
                'uploaded_by_type' => 'user',
                'status' => 'uploaded',
                'external_id' => $document->external_id,
                'external_system' => $document->external_system,
                'parent_document_id' => $document->id,
                'metadata' => json_encode([
                    'edited_fields' => $validated['field_values'],
                    'edited_at' => now()->toISOString(),
                    'edited_by' => $user->name,
                ]),
            ]);

            // Mark old document as superseded
            $document->update([
                'is_superseded' => true,
                'superseded_by_id' => $editedDocument->id,
                'superseded_at' => now(),
            ]);

            Log::info('PDF document marked as edited', [
                'application_id' => $application->id,
                'original_document_id' => $document->id,
                'edited_document_id' => $editedDocument->id,
                'field_count' => count($validated['field_values']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document edited successfully',
                'edited_document_id' => $editedDocument->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save PDF edits', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save edits: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get editable fields based on application data
     */
    private function getEditableFields(Application $application): array
    {
        return [
            [
                'name' => 'merchant_name',
                'value' => $application->name ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'trading_name',
                'value' => $application->trading_name ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'company_number',
                'value' => $application->company_number ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'registered_address',
                'value' => $application->registered_address ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'contact_email',
                'value' => $application->account->email ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'contact_phone',
                'value' => $application->account->mobile ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'transaction_percentage',
                'value' => $application->transaction_percentage ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'transaction_fixed_fee',
                'value' => $application->transaction_fixed_fee ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'monthly_fee',
                'value' => $application->monthly_fee ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'monthly_minimum',
                'value' => $application->monthly_minimum ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
            [
                'name' => 'setup_fee',
                'value' => $application->setup_fee ?? '',
                'type' => 'Text',
                'readonly' => false,
            ],
        ];
    }

    /**
     * Check document access permissions
     */
    private function checkDocumentAccess(Application $application, ApplicationDocument $document): void
    {
        $isAccount = auth()->guard('account')->check();
        
        if ($isAccount) {
            if ($application->account_id !== auth()->guard('account')->id()) {
                abort(403);
            }
        } elseif (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
                abort(403);
            }
        } else {
            abort(403);
        }
    }

    public function download(Application $application, ApplicationDocument $document): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Check permissions
        $this->checkDocumentAccess($application, $document);
    
        // Verify document belongs to application
        if ($document->application_id !== $application->id) {
            abort(404);
        }
    
        // Check if document has been dumped
        if ($document->isDumped()) {
            abort(410, 'This document has been removed as part of our data retention policy (30 days after application approval).');
        }
    
        return Storage::disk('public')->download($document->file_path, $document->original_filename);
    }

    /**
     * View a document inline (returns base64 encoded content for modal viewing)
     */
    public function view(Application $application, ApplicationDocument $document): JsonResponse
    {
        // Check permissions
        $this->checkDocumentAccess($application, $document);

        // Verify document belongs to application
        if ($document->application_id !== $application->id) {
            abort(404);
        }

        // Check if document is dumped
        if ($document->isDumped()) {
            return response()->json([
                'success' => false,
                'message' => 'This document has been removed as part of our data retention policy (30 days after application approval).',
            ], 410);
        }

        try {
            if (!Storage::disk('public')->exists($document->file_path)) {
                Log::warning('Document file not found', [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Document file not found',
                ], 404);
            }

            $content = Storage::disk('public')->get($document->file_path);
            $base64 = base64_encode($content);

            return response()->json([
                'success' => true,
                'filename' => $document->original_filename,
                'mime_type' => $document->document_type,
                'content' => $base64,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load document for viewing', [
                'document_id' => $document->id,
                'file_path' => $document->file_path,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load document: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Application $application, ApplicationDocument $document): RedirectResponse
    {
        // Only admins and users who created the application can delete documents
        if (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
                abort(403, 'Only administrators can delete documents.');
            }
        } else {
            abort(403, 'Only administrators can delete documents.');
        }

        // Verify document belongs to application
        if ($document->application_id !== $application->id) {
            abort(404);
        }

        // Delete file from storage
        Storage::disk('public')->delete($document->file_path);

        // Delete database record
        $document->delete();

        return Redirect::back()->with('success', 'Document deleted successfully.');
    }

    public function removeAdditionalDocumentRequirement(Application $application, ApplicationAdditionalDocument $additionalDocument): RedirectResponse
    {
        // Only admins and users who created the application can remove requirements
        if (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            if (!$user->isAdmin() && $application->account->user_id !== $user->id) {
                abort(403, 'Only administrators can remove document requirements.');
            }
        } else {
            abort(403, 'Only administrators can remove document requirements.');
        }

        // Verify additional document belongs to application
        if ($additionalDocument->application_id !== $application->id) {
            abort(404);
        }

        // Delete any uploaded documents in this category
        $categoryName = "additional_requested_{$additionalDocument->id}";
        $uploadedDocs = $application->documents()->where('document_category', $categoryName)->get();
        
        foreach ($uploadedDocs as $doc) {
            Storage::disk('public')->delete($doc->file_path);
            $doc->delete();
        }

        // Delete the requirement
        $additionalDocument->delete();

        // Refresh and check if all documents are now complete
        $application->load('documents', 'additionalDocuments');
        
        if ($application->status->hasAllRequiredDocuments()) {
            $currentStep = $application->status->current_step;
            
            // Only auto-transition if in 'created' or 'contract_sent' status
            if (in_array($currentStep, ['created', 'contract_sent'])) {
                $application->status->transitionTo('documents_uploaded', 'All required documents uploaded');
                event(new AllDocumentsUploadedEvent($application));
            }
        }

        return Redirect::back()->with('success', 'Document requirement removed successfully.');
    }

}