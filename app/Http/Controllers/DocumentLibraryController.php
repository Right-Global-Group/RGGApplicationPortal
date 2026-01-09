<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\DocuSignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Request;
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
                }, 'status', 'account'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $user = auth()->guard('web')->user();
            if ($user->isAdmin()) {
                $applications = Application::with(['documents' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }, 'status', 'account'])
                ->orderBy('created_at', 'desc')
                ->get();
            } else {
                $applications = Application::whereHas('account', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->with(['documents' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }, 'status', 'account'])
                ->orderBy('created_at', 'desc')
                ->get();
            }
        }
    
        // Check incoming filter parameter
        $applicationFilter = Request::input('application');

        // Apply application/account name filter
        if ($applicationFilter) {
    
            $applications = $applications->filter(function ($app) use ($applicationFilter) {
                // Handle case where account might be null
                $accountName = $app->account ? $app->account->name : '';
                $tradingName = $app->trading_name ?? '';
                
                $matchesName = stripos($app->name, $applicationFilter) !== false;
                $matchesAccount = stripos($accountName, $applicationFilter) !== false;
                $matchesTrading = stripos($tradingName, $applicationFilter) !== false;
                
                return $matchesName || $matchesAccount || $matchesTrading;
            });
            
            Log::info('Filter applied', [
                'total_apps_after' => $applications->count(),
            ]);
        } else {
            Log::info('No filter applied - showing all applications');
        }
    
        // Map applications with their documents
        $applicationsWithDocs = $applications->map(function ($application) {
            $envelopeId = $application->status?->docusign_envelope_id ?? null;
    
            return [
                'id' => $application->id,
                'name' => $application->name,
                'account_name' => $application->account?->name ?? 'Unknown',
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
                    'dumped_at' => $doc->dumped_at?->format('Y-m-d H:i'),
                    'dumped_reason' => $doc->dumped_reason,
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
    
        // Get all applications for the dropdown (for upload modal)
        $allApplications = $isAccount 
            ? Application::with('account')
                ->where('account_id', auth()->guard('account')->id())
                ->orderBy('name')
                ->get()
                ->map(fn($app) => [
                    'id' => $app->id,
                    'name' => $app->name . ' - ' . ($app->account?->name ?? 'Unknown'),
                    'account_name' => $app->account?->name ?? 'Unknown',
                ])
            : ($user->isAdmin() 
                ? Application::with('account')
                    ->orderBy('name')
                    ->get()
                    ->map(fn($app) => [
                        'id' => $app->id,
                        'name' => $app->name . ' - ' . ($app->account?->name ?? 'Unknown'),
                        'account_name' => $app->account?->name ?? 'Unknown',
                    ])
                : Application::with('account')
                    ->whereHas('account', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->orderBy('name')
                    ->get()
                    ->map(fn($app) => [
                        'id' => $app->id,
                        'name' => $app->name . ' - ' . ($app->account?->name ?? 'Unknown'),
                        'account_name' => $app->account?->name ?? 'Unknown',
                    ])
            );
    
        return Inertia::render('Documents/Index', [
            'applications' => $applicationsWithDocs->values(),
            'allApplications' => $allApplications,
            'is_account' => $isAccount,
            'filters' => Request::only(['application']),
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
     * Upload document from library
     */
    public function uploadDocument(): RedirectResponse
    {
        // Only users can upload from library
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot upload documents from the library.');
        }

        $validated = Request::validate([
            'application_id' => ['required', 'exists:applications,id'],
            'document_category' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xlsx,xls,csv,jpg,jpeg,png', 'max:51200'], // 50MB
        ]);

        $application = Application::findOrFail($validated['application_id']);

        // Store the file
        $file = Request::file('file');
        $path = $file->store('application_documents/' . $application->id, 'public');

        // Create document record
        ApplicationDocument::create([
            'application_id' => $application->id,
            'document_type' => $file->getMimeType(),
            'document_category' => $validated['document_category'],
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by' => auth()->id(),
            'uploaded_by_type' => 'user',
            'is_library_uploaded' => true,
        ]);

        return redirect()->back()->with('success', 'Document uploaded successfully.');
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