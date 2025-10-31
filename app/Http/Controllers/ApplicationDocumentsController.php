<?php

namespace App\Http\Controllers;

use App\Events\AllDocumentsUploadedEvent;
use App\Events\DocumentUploadedEvent;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationAdditionalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;

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
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xlsx,xls,csv,jpg,jpeg,png', 'max:10240'],
        ]);
    
        // Validate category is valid for this application
        $validCategories = ApplicationDocument::getAllValidCategoriesForApplication($application);
        if (!in_array($validated['document_category'], $validCategories)) {
            return Redirect::back()->withErrors(['document_category' => 'Invalid document category.']);
        }
    
        $file = Request::file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('applications/' . $application->id . '/documents', $filename, 'private');
    
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
    
        // Check if this is an additional document and mark it as uploaded
        if (str_starts_with($validated['document_category'], 'additional_requested_')) {
            $docId = str_replace('additional_requested_', '', $validated['document_category']);
            $additionalDoc = $application->additionalDocuments()->find($docId);
            
            if ($additionalDoc) {
                $additionalDoc->update([
                    'is_uploaded' => true,
                    'uploaded_at' => now(),
                ]);
            }
        }
    
        // Fire document uploaded event
        event(new DocumentUploadedEvent($document));
    
        // Check if all required documents are now uploaded
        if ($application->status->hasAllRequiredDocuments()) {
            $currentStep = $application->status->current_step;
            
            // Only auto-transition if in 'created' or 'contract_sent' status
            if (in_array($currentStep, ['created', 'contract_sent'])) {
                $application->status->transitionTo('documents_uploaded', 'All required documents uploaded');
                event(new AllDocumentsUploadedEvent($application));
            }
        }
    
        return Redirect::back()->with('success', 'Document uploaded successfully.');
    }

    public function download(Application $application, ApplicationDocument $document): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Check permissions
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

        // Verify document belongs to application
        if ($document->application_id !== $application->id) {
            abort(404);
        }

        return Storage::disk('private')->download($document->file_path, $document->original_filename);
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
        Storage::disk('private')->delete($document->file_path);

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
            Storage::disk('private')->delete($doc->file_path);
            $doc->delete();
        }

        // Delete the requirement
        $additionalDocument->delete();

        // Check if all documents are now complete
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