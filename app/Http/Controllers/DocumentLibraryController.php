<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\DocuSignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentLibraryController extends Controller
{
    public function __construct(
        private DocuSignService $docuSignService
    ) {}

    public function index(): Response
    {
        $isAccount = auth()->guard('account')->check();
        
        // Get applications based on user type
        if ($isAccount) {
            $applications = Application::where('account_id', auth()->guard('account')->id())
                ->with(['documents' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }, 'status'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $user = auth()->guard('web')->user();
            if ($user->isAdmin()) {
                $applications = Application::with(['documents' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }, 'status'])
                ->orderBy('created_at', 'desc')
                ->get();
            } else {
                $applications = Application::whereHas('account', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->with(['documents' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }, 'status'])
                ->orderBy('created_at', 'desc')
                ->get();
            }
        }

        // Map applications with their documents
        $applicationsWithDocs = $applications->map(function ($application) {
            $envelopeId = $application->status->docusign_envelope_id;

            return [
                'id' => $application->id,
                'name' => $application->name,
                'trading_name' => $application->trading_name,
                'created_at' => $application->created_at->format('Y-m-d'),
                'docusign_envelope_id' => $envelopeId,
                'has_docusign' => !!$envelopeId,
                'documents' => $application->documents->map(fn($doc) => [
                    'id' => $doc->id,
                    'category' => $doc->document_category,
                    'filename' => $doc->original_filename,
                    'mime_type' => $doc->document_type,
                    'uploaded_at' => $doc->created_at->format('Y-m-d H:i'),
                    'download_url' => route('applications.documents.download', [
                        'application' => $application->id,
                        'document' => $doc->id,
                    ]),
                    'view_url' => route('applications.documents.view', [
                        'application' => $application->id,
                        'document' => $doc->id,
                    ]),
                ]),
            ];
        });

        return Inertia::render('Documents/Index', [
            'applications' => $applicationsWithDocs,
            'is_account' => $isAccount,
        ]);
    }
    
    /**
     * View a document inline (returns base64 encoded content for modal viewing)
     */
    private function view(Application $application, ApplicationDocument $document): JsonResponse
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

        try {
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
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load document',
            ], 500);
        }
    }

    /**
     * Download an uploaded document (streaming approach)
     */
    public function downloadDocument(Application $application, ApplicationDocument $document)
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

        // Check if file exists
        if (empty($document->file_path)) {
            abort(404, 'Document file not found');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'Document file not found in storage');
        }

        try {
            $content = Storage::disk('public')->get($document->file_path);
            $mimeType = Storage::disk('public')->mimeType($document->file_path);
            
            return response()->streamDownload(
                function() use ($content) {
                    echo $content;
                },
                $document->original_filename,
                [
                    'Content-Type' => $mimeType,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to download document', [
                'document_id' => $document->id,
                'file_path' => $document->file_path,
                'error' => $e->getMessage(),
            ]);
            
            abort(500, 'Failed to download document');
        }
    }

    /**
     * View a document inline (returns base64 encoded content for modal viewing)
     */
    public function viewDocument(Application $application, ApplicationDocument $document): JsonResponse
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

        try {
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
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load document',
            ], 500);
        }
    }

    /**
     * Download DocuSign contract
     */
    public function downloadDocuSignContract(Application $application)
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

        $envelopeId = $application->status->docusign_envelope_id;
        
        if (!$envelopeId) {
            abort(404, 'No DocuSign contract found for this application');
        }

        try {
            $pdfContent = $this->docuSignService->downloadEnvelopeDocument($envelopeId, '2');
            
            return response()->streamDownload(
                function() use ($pdfContent) {
                    echo base64_decode($pdfContent);
                },
                "Signed_Contract_{$application->name}.pdf",
                [
                    'Content-Type' => 'application/pdf',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to download DocuSign contract', [
                'envelope_id' => $envelopeId,
                'error' => $e->getMessage(),
            ]);
            
            abort(500, 'Failed to download contract');
        }
    }

    /**
     * View DocuSign contract inline
     */
    public function viewDocuSignContract(Application $application): JsonResponse
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

        $envelopeId = $application->status->docusign_envelope_id;
        
        if (!$envelopeId) {
            return response()->json([
                'success' => false,
                'message' => 'No DocuSign contract found',
            ], 404);
        }

        try {
            $pdfContent = $this->docuSignService->downloadEnvelopeDocument($envelopeId, '2');
            
            return response()->json([
                'success' => true,
                'filename' => "Signed_Contract_{$application->name}.pdf",
                'mime_type' => 'application/pdf',
                'content' => $pdfContent,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load DocuSign contract', [
                'envelope_id' => $envelopeId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load contract',
            ], 500);
        }
    }
}