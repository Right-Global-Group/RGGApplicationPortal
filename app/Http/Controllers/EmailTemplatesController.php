<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplatesController extends Controller
{
    private string $templatesPath;

    public function __construct()
    {
        $this->templatesPath = resource_path('views/emails');
    }

    /**
     * Display a listing of all email templates
     */
    public function index(): Response
    {
        // Only admins can access email templates
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $templates = $this->getEmailTemplates();

        return Inertia::render('EmailTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show a specific email template for editing
     */
    public function edit(string $template): Response
    {
        // Only admins can access email templates
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $filePath = $this->templatesPath . '/' . $template . '.blade.php';

        if (!File::exists($filePath)) {
            abort(404, 'Template not found.');
        }

        $content = File::get($filePath);

        return Inertia::render('EmailTemplates/Edit', [
            'template' => [
                'name' => $template,
                'display_name' => $this->formatTemplateName($template),
                'content' => $content,
                'path' => $filePath,
            ],
            'allTemplates' => $this->getEmailTemplates(),
        ]);
    }

    /**
     * Update a specific email template (AJAX version)
     */
    public function update(string $template)
    {
        // Only admins can update email templates
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = Request::validate([
            'content' => ['required', 'string'],
        ]);

        $filePath = $this->templatesPath . '/' . $template . '.blade.php';

        if (!File::exists($filePath)) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        // Create backup before updating
        $backupPath = $this->templatesPath . '/backups';
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $backupFile = $backupPath . '/' . $template . '_' . date('Y-m-d_H-i-s') . '.blade.php';
        File::copy($filePath, $backupFile);

        // Update the template file
        $bytesWritten = File::put($filePath, $validated['content']);

        if ($bytesWritten === false) {
            return response()->json(['error' => 'Failed to save template file. Check file permissions.'], 500);
        }

        // Clear view cache to ensure changes take effect
        $this->clearViewCache();

        // Touch the file to update timestamp
        touch($filePath);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully.',
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Reset template to original/default version
     */
    public function reset(string $template)
    {
        // Only admins can reset email templates
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $filePath = $this->templatesPath . '/' . $template . '.blade.php';

        if (!File::exists($filePath)) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        // Check if there are backups to restore from
        $backupPath = $this->templatesPath . '/backups';
        
        if (!File::exists($backupPath)) {
            return response()->json(['error' => 'No backups available for this template.'], 400);
        }

        // Get all backups for this template, sorted by date (newest first)
        $backups = collect(File::files($backupPath))
            ->filter(fn($file) => str_starts_with($file->getFilename(), $template . '_'))
            ->sortByDesc(fn($file) => $file->getMTime())
            ->values();

        if ($backups->isEmpty()) {
            return response()->json(['error' => 'No backups available for this template.'], 400);
        }

        // Use the most recent backup
        $latestBackup = $backups->first();
        
        // Create a backup of current version before resetting
        $currentBackupFile = $backupPath . '/' . $template . '_before-reset_' . date('Y-m-d_H-i-s') . '.blade.php';
        File::copy($filePath, $currentBackupFile);

        // Restore from backup
        File::copy($latestBackup->getPathname(), $filePath);

        // Clear view cache
        $this->clearViewCache();

        // Touch the file to update timestamp
        touch($filePath);

        return response()->json([
            'success' => true,
            'message' => 'Template reset to previous backup from ' . date('Y-m-d H:i', $latestBackup->getMTime()),
            'content' => File::get($filePath),
        ]);
    }

    /**
     * Get preview HTML via AJAX
     */
    public function previewAjax(string $template)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    
        $filePath = $this->templatesPath . '/' . $template . '.blade.php';
    
        if (!File::exists($filePath)) {
            return response()->json(['error' => 'Template not found'], 404);
        }
    
        // Clear view cache
        $this->clearViewCache();
    
        $sampleData = $this->getSampleData($template);
    
        try {
            // Check for unsaved content (from textarea)
            $rawContent = Request::input('content');
            $isPreviewingUnsaved = !empty($rawContent);
    
            if ($isPreviewingUnsaved) {
                // Create a temporary file to safely render the unsaved content
                $tempPath = storage_path("framework/views/_preview_{$template}.blade.php");
                File::put($tempPath, $rawContent);
    
                $view = view()->file($tempPath, $sampleData);
            } else {
                // Fallback: render from saved file
                $view = view('emails.' . $template, $sampleData);
            }
    
            $html = $view->render();
    
            // Clean up temp file (optional)
            if ($isPreviewingUnsaved && File::exists($tempPath)) {
                @unlink($tempPath);
            }
    
            return response()->json([
                'html' => $html,
                'timestamp' => now()->timestamp,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'html' => '<div style="color: red; padding: 20px; font-family: sans-serif;">
                    <h3>Error Rendering Template</h3>
                    <p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                    <p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>
                    <p><strong>Line:</strong> ' . $e->getLine() . '</p>
                </div>',
                'error' => $e->getMessage(),
            ], 500);
        }
    }    

    /**
     * Clear all view caches
     */
    private function clearViewCache(): void
    {
        // Clear compiled views
        $compiled = storage_path('framework/views');
        if (File::exists($compiled)) {
            try {
                $files = File::files($compiled);
                foreach ($files as $file) {
                    @unlink($file->getPathname());
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Clear view cache via artisan
        try {
            \Artisan::call('view:clear');
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Get all email templates from the emails directory
     */
    private function getEmailTemplates(): array
    {
        $files = File::files($this->templatesPath);
        $templates = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && str_contains($file->getFilename(), '.blade.php')) {
                $name = str_replace('.blade.php', '', $file->getFilename());
                $templates[] = [
                    'name' => $name,
                    'display_name' => $this->formatTemplateName($name),
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        // Sort by name
        usort($templates, fn($a, $b) => strcmp($a['display_name'], $b['display_name']));

        return $templates;
    }

    /**
     * Format template filename to display name
     */
    private function formatTemplateName(string $name): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Get sample data for template preview
     */
    private function getSampleData(string $template): array
    {
        // Common sample data that many templates use
        $commonData = [
            // Account info
            'name' => 'John Doe',  // Used by account-credentials
            'account_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            
            // Application info
            'application_name' => 'Sample Application Ltd',
            'trading_name' => 'Sample Trading Ltd',
            
            // User info
            'user_name' => 'Admin User',
            'created_by' => 'Admin User',
            
            // URLs
            'application_url' => URL::to('/applications/1/status'),
            'status_url' => URL::to('/applications/1/status'),
            'login_url' => URL::to('/account/login'),
            'tracking_url' => URL::to('/email/track/1'),
            
            // Dates
            'created_at' => now()->format('Y-m-d H:i'),
            'confirmed_at' => now()->format('Y-m-d H:i'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ];

        // Template-specific sample data
        $specificData = match($template) {
            'account-credentials' => [
                'password' => 'SamplePassword123!',
            ],
            'application-created' => [
                'setup_fee' => 500.00,
                'transaction_percentage' => 2.5,
                'transaction_fixed_fee' => 0.20,
                'monthly_fee' => 50.00,
                'monthly_minimum' => 100.00,
                'service_fee' => 25.00,
            ],
            'fees-changed' => [
                'parent_application_name' => 'Previous Application Name',
                'old_setup_fee' => 400.00,
                'new_setup_fee' => 500.00,
                'old_transaction_percentage' => 2.0,
                'new_transaction_percentage' => 2.5,
                'old_transaction_fixed_fee' => 0.15,
                'new_transaction_fixed_fee' => 0.20,
                'old_monthly_fee' => 40.00,
                'new_monthly_fee' => 50.00,
                'old_monthly_minimum' => 80.00,
                'new_monthly_minimum' => 100.00,
                'old_service_fee' => 20.00,
                'new_service_fee' => 25.00,
                'setup_fee' => 500.00,
                'transaction_percentage' => 2.5,
                'transaction_fixed_fee' => 0.20,
                'monthly_fee' => 50.00,
                'monthly_minimum' => 100.00,
                'service_fee' => 25.00,
            ],
            'fees-confirmation-reminder' => [
                'setup_fee' => 500.00,
                'transaction_percentage' => 2.5,
                'transaction_fixed_fee' => 0.20,
                'monthly_fee' => 50.00,
                'monthly_minimum' => 100.00,
                'service_fee' => 25.00,
            ],
            'additional-info-requested' => [
                'requested_info' => 'Please provide your business registration documents and proof of address for the registered business location.',
            ],
            'application-approved' => [
                'next_steps' => 'We will send you an invoice for the setup fee shortly. Once payment is received, we will proceed with gateway integration.',
                'setup_fee' => 500.00,
            ],
            'document-uploaded' => [
                'document_name' => 'Business Registration Certificate',
                'document_category' => 'Company Documents',
                'uploaded_at' => now()->format('Y-m-d H:i'),
            ],
            'all-documents-uploaded' => [
                'documents_count' => 5,
                'documents' => [
                    ['category' => 'Business Registration', 'count' => 1],
                    ['category' => 'Proof of Address', 'count' => 1],
                    ['category' => 'Bank Statements', 'count' => 3],
                    ['category' => 'ID Documentation', 'count' => 2],
                    ['category' => 'Processing Statements', 'count' => 1],
                ],
            ],
            'merchant-contract-ready' => [
                'signing_url' => URL::to('/docusign/sign/abc123'),
            ],
            'gateway-partner-contract-ready' => [
                'gateway_partner_name' => 'Cardstream',
                'signing_url' => URL::to('/docusign/sign/xyz789'),
                'setup_fee' => 500.00,
                'transaction_percentage' => 2.5,
                'transaction_fixed_fee' => 0.20,
                'monthly_fee' => 50.00,
            ],
            'wordpress-credentials-request' => [
                // Uses common data (application_name, account_name, application_url)
            ],
            'wordpress-credentials-reminder' => [
                // Uses common data (application_name, account_name, application_url)
            ],
            default => [],
        };

        return array_merge($commonData, $specificData);
    }
}