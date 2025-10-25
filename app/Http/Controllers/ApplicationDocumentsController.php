<?php

namespace App\Http\Controllers;

use App\Events\AllDocumentsUploadedEvent;
use App\Events\DocumentUploadedEvent;
use App\Models\Application;
use App\Models\ApplicationDocument;
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
            'document_category' => ['required', 'string', 'in:' . implode(',', array_keys(ApplicationDocument::getRequiredCategories()))],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xlsx,xls,csv,jpg,jpeg,png', 'max:10240'], // 10MB max
        ]);

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

        // Fire document uploaded event (sends email to user)
        event(new DocumentUploadedEvent($document));

        // Check if all required documents are now uploaded
        if ($application->status->hasAllRequiredDocuments()) {
            // Update status to documents_uploaded
            $application->status->transitionTo('documents_uploaded', 'All required documents uploaded');
            
            // Fire all documents uploaded event (sends email to user)
            event(new AllDocumentsUploadedEvent($application));
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
}