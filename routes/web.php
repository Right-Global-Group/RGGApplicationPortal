<?php

use App\Http\Controllers\AccountAuthController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\ApplicationsController;
use App\Http\Controllers\ApplicationDocumentsController;
use App\Http\Controllers\ApplicationStatusController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocuSignWebhookController;
use App\Http\Controllers\ProgressTrackerController;
use App\Http\Controllers\EmailTemplatesController;
use App\Http\Controllers\DocumentLibraryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\MerchantImportController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\XeroExportController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::post('/clear-login-flag', function () {
    session()->forget('just_logged_in');
    return response()->json(['success' => true]);
})->middleware(['auth:web,account']);

// Account Authentication Routes
Route::get('/account/login', [AccountAuthController::class, 'showLoginForm'])->name('account.login');
Route::post('/account/login', [AccountAuthController::class, 'login']);
Route::delete('/account/logout', [AccountAuthController::class, 'logout'])->name('account.logout');

// User Authentication Routes
Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('login', [AuthenticatedSessionController::class, 'store']);
Route::delete('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// DocuSign Webhooks (Public - No Auth Required)
Route::post('/webhooks/docusign/merchant', [DocuSignWebhookController::class, 'handleMerchantWebhook'])
    ->name('webhooks.docusign.merchant');
Route::post('/webhooks/docusign/cardstream', [DocuSignWebhookController::class, 'handleCardStreamWebhook'])
    ->name('webhooks.docusign.cardstream');

// Protected Routes (Both User and Account Guards)
Route::middleware(['auth:web,account'])->group(function () {
    
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Email Templates
    Route::get('/email-templates', [EmailTemplatesController::class, 'index'])
        ->name('email-templates.index');
    Route::get('/email-templates/{template}/edit', [EmailTemplatesController::class, 'edit'])
        ->name('email-templates.edit');
    Route::put('/email-templates/{template}', [EmailTemplatesController::class, 'update'])
        ->name('email-templates.update');
    Route::post('/email-templates/{template}/reset', [EmailTemplatesController::class, 'reset'])
        ->name('email-templates.reset');
    Route::get('/email-templates/{template}/preview', [EmailTemplatesController::class, 'previewAjax'])
        ->name('email-templates.preview');
    Route::post('/email-templates/{template}/preview', [EmailTemplatesController::class, 'previewAjax'])
        ->name('email-templates.preview');

    // Document Library Routes
    Route::get('/document-library', [DocumentLibraryController::class, 'index'])
        ->name('document-library.index');
    Route::post('/document-library/upload', [DocumentLibraryController::class, 'uploadDocument'])
        ->name('document-library.upload');

    // Legacy route (kept for backwards compatibility)
    Route::get('/documents', [DocumentLibraryController::class, 'index'])
        ->name('documents.index');

    // Accounts Routes
    Route::prefix('accounts')->group(function () {
        Route::get('/', [AccountsController::class, 'index'])->name('accounts');
        Route::get('/create', [AccountsController::class, 'create'])->name('accounts.create')->middleware('role:admin');
        Route::post('/', [AccountsController::class, 'store'])->middleware('role:admin');
        Route::get('/{account}/edit', [AccountsController::class, 'edit'])->name('accounts.edit');
        Route::put('/{account}', [AccountsController::class, 'update']);
        Route::delete('/{account}', [AccountsController::class, 'destroy'])->middleware('role:admin');
        Route::put('/{account}/restore', [AccountsController::class, 'restore'])->middleware('role:admin');
        Route::get('/{account}/photo', [AccountsController::class, 'showPhoto'])->name('accounts.photo');
        
        // Account Email Actions
        Route::post('/{account}/send-credentials', [AccountsController::class, 'sendCredentialsEmail'])->middleware('role:admin');
        Route::post('/{account}/set-credentials-reminder', [AccountsController::class, 'setCredentialsReminder'])
            ->name('accounts.set-credentials-reminder')
            ->middleware('role:admin');
        Route::post('/{account}/cancel-credentials-reminder', [AccountsController::class, 'cancelCredentialsReminder'])
            ->name('accounts.cancel-credentials-reminder')
            ->middleware('role:admin');
    });

    // Applications Routes
    Route::prefix('applications')->group(function () {
        Route::get('/', [ApplicationsController::class, 'index'])->name('applications');
        Route::get('/create', [ApplicationsController::class, 'create'])->name('applications.create');
        Route::post('/', [ApplicationsController::class, 'store']);
        Route::get('/{application}/edit', [ApplicationsController::class, 'edit'])->name('applications.edit');
        Route::put('/{application}', [ApplicationsController::class, 'update']);
        Route::delete('/{application}', [ApplicationsController::class, 'destroy']);
        Route::put('/{application}/restore', [ApplicationsController::class, 'restore']);
        
        // Application Status & Actions
        Route::get('/{application}/status', [ApplicationStatusController::class, 'show'])->name('applications.status');
        Route::post('/{application}/confirm-fees', [ApplicationStatusController::class, 'confirmFees'])->name('applications.confirm-fees');
        Route::post('/{application}/change-fees', [ApplicationsController::class, 'changeFees'])->name('applications.change-fees');
        Route::put('/{application}/update-fees', [ApplicationsController::class, 'updateFees'])->name('applications.update-fees');
        Route::post('/{application}/update-step', [ApplicationStatusController::class, 'updateStep']);
        
        // Merchant Contract (DocuSign)
        Route::post('/{application}/send-contract', [ApplicationStatusController::class, 'sendContractLink']);
        Route::get('/{application}/docusign-callback', [ApplicationStatusController::class, 'docusignCallback'])->name('applications.docusign-callback');
        
        // Contract reminder routes
        Route::post('/{application}/send-contract-reminder', [ApplicationStatusController::class, 'sendContractReminder'])
            ->name('applications.send-contract-reminder');
        Route::post('/{application}/set-contract-reminder', [ApplicationStatusController::class, 'setContractReminder'])
            ->name('applications.set-contract-reminder');
        Route::post('/{application}/cancel-contract-reminder', [ApplicationStatusController::class, 'cancelContractReminder'])
            ->name('applications.cancel-contract-reminder');
        
        // Submit to CardStream
        Route::post('/{application}/submit-to-cardstream', [ApplicationStatusController::class, 'submitToCardStream'])
            ->name('applications.submit-to-cardstream');
        
        // Gateway Partner Contract (DocuSign)
        Route::post('/{application}/send-gateway-contract', [ApplicationStatusController::class, 'sendGatewayContract'])->name('applications.send-gateway-contract');
        Route::get('/{application}/gateway-docusign-callback', [ApplicationStatusController::class, 'gatewayDocusignCallback'])->name('applications.gateway-docusign-callback');
        
        // Gateway Details
        Route::post('/{application}/gateway-details', [ApplicationStatusController::class, 'storeGatewayDetails'])->name('applications.gateway-details');
        
        // WordPress Credentials
        Route::post('/{application}/wordpress-credentials', [ApplicationStatusController::class, 'storeWordPressCredentials'])->name('applications.wordpress-credentials');
        Route::post('/{application}/send-wordpress-reminder', [ApplicationStatusController::class, 'sendWordPressCredentialsReminder'])->name('applications.send-wordpress-reminder');
        
        // Other Status Actions
        Route::post('/{application}/send-approval-email', [ApplicationStatusController::class, 'sendApprovalEmail']);
        Route::post('/{application}/request-additional-info', [ApplicationStatusController::class, 'requestAdditionalInfo']);
        Route::post('/{application}/set-additional-info-reminder', [ApplicationStatusController::class, 'setAdditionalInfoReminder'])
            ->name('applications.set-additional-info-reminder')
            ->middleware('role:admin');
        Route::post('/{application}/cancel-additional-info-reminder', [ApplicationStatusController::class, 'cancelAdditionalInfoReminder'])
            ->name('applications.cancel-additional-info-reminder')
            ->middleware('role:admin');
        Route::post('/{application}/mark-approved', [ApplicationStatusController::class, 'markAsApproved']);
        
        // Application Email Reminders
        Route::post('/{application}/set-email-reminder', [ApplicationsController::class, 'setEmailReminder']);
        Route::post('/{application}/cancel-email-reminder', [ApplicationsController::class, 'cancelEmailReminder']);
        Route::post('/{application}/send-fees-reminder', [ApplicationStatusController::class, 'sendFeesConfirmationReminder'])->name('applications.send-fees-reminder');
        Route::post('/{application}/set-fees-reminder', [ApplicationStatusController::class, 'setFeesConfirmationReminder'])->name('applications.set-fees-reminder');
        Route::post('/{application}/cancel-fees-reminder', [ApplicationStatusController::class, 'cancelFeesConfirmationReminder'])->name('applications.cancel-fees-reminder');

        // Document Upload
        Route::post('/{application}/documents', [ApplicationDocumentsController::class, 'store'])
            ->name('applications.documents.store');
        Route::delete('/{application}/documents/{document}', [ApplicationDocumentsController::class, 'destroy'])
            ->name('applications.documents.destroy');
        Route::post('/{application}/mark-documents-approved', [ApplicationStatusController::class, 'markDocumentsApproved'])
            ->name('applications.mark-documents-approved');
        Route::delete('/{application}/additional-documents/{additionalDocument}/requirement', [ApplicationDocumentsController::class, 'removeAdditionalDocumentRequirement'])
            ->name('applications.additional-documents.remove-requirement');

        // Invoice management
        Route::post('/{application}/send-invoice-reminder', [ApplicationStatusController::class, 'sendInvoiceReminder'])
            ->name('applications.send-invoice-reminder');
        Route::post('/{application}/mark-invoice-paid', [ApplicationStatusController::class, 'markInvoiceAsPaid'])
            ->name('applications.mark-invoice-paid');

        // CardStream credentials
        Route::post('/{application}/send-cardstream-credentials', [ApplicationsController::class, 'sendCardStreamCredentials'])
            ->name('applications.send-cardstream-credentials');
        Route::post('/{application}/cancel-cardstream-reminder', [ApplicationsController::class, 'cancelCardStreamReminder'])
            ->name('applications.cancel-cardstream-reminder');

        // Gateway integration
        Route::post('/{application}/mark-gateway-integrated', [ApplicationsController::class, 'markGatewayIntegrated'])
            ->name('applications.mark-gateway-integrated');

        // WordPress credentials
        Route::post('/{application}/request-wordpress-credentials', [ApplicationsController::class, 'requestWordPressCredentials'])
            ->name('applications.request-wordpress-credentials');
        Route::post('/{application}/save-wordpress-credentials', [ApplicationsController::class, 'saveWordPressCredentials'])
            ->name('applications.save-wordpress-credentials');
        Route::post('/{application}/cancel-wordpress-reminder', [ApplicationsController::class, 'cancelWordPressReminder'])
            ->name('applications.cancel-wordpress-reminder');

        // Make account live
        Route::post('/{application}/make-account-live', [ApplicationsController::class, 'makeAccountLive'])
            ->name('applications.make-account-live');

        // Account message routes (only for accounts)
        Route::post('/{application}/send-account-message', [ApplicationStatusController::class, 'sendAccountMessage'])
            ->name('applications.send-account-message');
        Route::post('/{application}/set-account-message-reminder', [ApplicationStatusController::class, 'setAccountMessageReminder'])
            ->name('applications.set-account-message-reminder');
        Route::post('/{application}/cancel-account-message-reminder', [ApplicationStatusController::class, 'cancelAccountMessageReminder'])
            ->name('applications.cancel-account-message-reminder');

        // Manual transition
        Route::post('/{application}/manual-transition', [ApplicationStatusController::class, 'manualTransition'])
            ->name('applications.manual-transition');

        // Document viewing and downloading routes (consolidated)
        Route::get('/{application}/documents/{document}/view', [DocumentLibraryController::class, 'viewDocument'])
            ->name('applications.documents.view');
        Route::get('/{application}/documents/{document}/download', [DocumentLibraryController::class, 'downloadDocument'])
            ->name('applications.documents.download');

        // DocuSign contract viewing and downloading
        Route::get('/{application}/docusign/view', [DocumentLibraryController::class, 'viewDocuSignContract'])
            ->name('applications.docusign.view');
        Route::get('/{application}/docusign/download', [DocumentLibraryController::class, 'downloadDocuSignContract'])
            ->name('applications.docusign.download');

        // PDF Editing Routes (for contract and application_form documents)
        Route::get('/{application}/documents/{document}/pdf-fields', [ApplicationDocumentsController::class, 'getPdfFields'])
            ->name('applications.documents.pdf-fields');
        
        Route::post('/{application}/documents/{document}/save-pdf-edits', [ApplicationDocumentsController::class, 'savePdfEdits'])
            ->name('applications.documents.save-pdf-edits');

        Route::post('/{application}/documents/{document}/upload-edited-pdf', [ApplicationDocumentsController::class, 'uploadEditedPdf'])
            ->name('applications.documents.upload-edited-pdf');
    });

    // Xero Export Routes
    Route::get('/invoices/{merchantName}/export-xero', [XeroExportController::class, 'exportMerchantInvoice'])
        ->name('invoices.export-xero');

    Route::get('/invoices/export-all-xero', [XeroExportController::class, 'exportAllMerchantsInvoices'])
        ->name('invoices.export-all-xero');

    Route::get('/invoices/export-selected-xero', [XeroExportController::class, 'exportSelectedMerchantsInvoices'])
        ->name('invoices.export-selected-xero');
        

    // Progress Tracker
    Route::get('/progress-tracker', [ProgressTrackerController::class, 'index'])->name('progress-tracker');

    Route::middleware('role:admin')->group(function () {
        Route::get('/invoices', [InvoicesController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{merchantName}', [InvoicesController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/upload', [InvoicesController::class, 'upload'])->name('invoices.upload');
        Route::delete('/invoices/{import}', [InvoicesController::class, 'destroy'])->name('invoices.destroy');
    });

    // Users Routes (Admin Only)
    Route::middleware('role:admin')->prefix('users')->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('users');
        Route::get('/create', [UsersController::class, 'create'])->name('users.create');
        Route::post('/', [UsersController::class, 'store']);
        Route::get('/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
        Route::put('/{user}', [UsersController::class, 'update']);
        Route::delete('/{user}', [UsersController::class, 'destroy']);
        Route::put('/{user}/restore', [UsersController::class, 'restore']);
        Route::get('/{user}/photo', [UsersController::class, 'showPhoto'])->name('users.photo');
    });

    // Settings Routes (Admin Only)
    Route::middleware('role:admin')->prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('settings');
        Route::post('/users/{user}/toggle-admin', [SettingsController::class, 'toggleUserAdmin']);
        Route::post('/users/{user}/permissions', [SettingsController::class, 'updateUserPermissions']);

        // Merchant Importer Routes
        Route::post('/merchant-importer/import', [MerchantImportController::class, 'import'])->name('settings.merchant-importer.import');
    });
});