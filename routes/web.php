<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ApplicationsController;
use App\Http\Controllers\ApplicationStatusController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocuSignWebhookController;
use App\Http\Controllers\GatewayIntegrationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProgressTrackerController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/webhooks/docusign', [DocuSignWebhookController::class, 'handle'])->name('docusign.webhook');

// DocuSign Callback (no auth required - user returns here after signing)
Route::get('/docusign/callback/{application}', [ApplicationStatusController::class, 'docusignCallback'])
    ->name('docusign.callback');

// Auth
Route::get('login', [AuthenticatedSessionController::class, 'create'])
    ->name('login')
    ->middleware('guest');

Route::post('login', [AuthenticatedSessionController::class, 'store'])
    ->name('login.store')
    ->middleware('guest');

Route::delete('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Applications
    Route::get('/applications', [ApplicationsController::class, 'index'])->name('applications');
    Route::get('/applications/create', [ApplicationsController::class, 'create'])->name('applications.create');
    Route::post('/applications', [ApplicationsController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}/edit', [ApplicationsController::class, 'edit'])->name('applications.edit');
    Route::put('/applications/{application}', [ApplicationsController::class, 'update'])->name('applications.update');
    Route::delete('/applications/{application}', [ApplicationsController::class, 'destroy'])->name('applications.destroy');
    Route::put('/applications/{application}/restore', [ApplicationsController::class, 'restore'])->name('applications.restore');

    // Application Status & Progress
    Route::get('/applications/{application}/status', [ApplicationStatusController::class, 'show'])->name('applications.status');
    Route::post('/applications/{application}/update-step', [ApplicationStatusController::class, 'updateStep'])->name('applications.update-step');
    Route::post('/applications/{application}/send-contract', [ApplicationStatusController::class, 'sendContractLink'])->name('applications.send-contract');
    Route::post('/applications/{application}/approve', [ApplicationStatusController::class, 'markAsApproved'])->name('applications.approve');
    Route::post('/applications/{application}/send-approval-email', [ApplicationStatusController::class, 'sendApprovalEmail'])->name('applications.send-approval-email');
    Route::post('/applications/{application}/request-additional-info', [ApplicationStatusController::class, 'requestAdditionalInfo'])->name('applications.request-additional-info');

    // Invoices
    Route::post('/applications/{application}/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.mark-paid');

    // Gateway Integration
    Route::post('/applications/{application}/gateway', [GatewayIntegrationController::class, 'store'])->name('gateway.store');
    Route::post('/gateway/{integration}/submit', [GatewayIntegrationController::class, 'submitToGateway'])->name('gateway.submit');
    Route::post('/gateway/{integration}/testing', [GatewayIntegrationController::class, 'markAsTesting'])->name('gateway.testing');
    Route::post('/gateway/{integration}/live', [GatewayIntegrationController::class, 'markAsLive'])->name('gateway.live');

    // Progress Tracker
    Route::get('/progress-tracker', [ProgressTrackerController::class, 'index'])->name('progress-tracker');

    // Accounts
    Route::get('/accounts', [AccountsController::class, 'index'])->name('accounts');
    Route::get('/accounts/create', [AccountsController::class, 'create'])->name('account.create');
    Route::post('/accounts', [AccountsController::class, 'store'])->name('account.store');
    Route::get('/accounts/{account}/edit', [AccountsController::class, 'edit'])->name('account.edit');
    Route::put('/accounts/{account}', [AccountsController::class, 'update'])->name('account.update');
    Route::delete('/accounts/{account}', [AccountsController::class, 'destroy'])->name('account.destroy');
    Route::put('/accounts/{account}/restore', [AccountsController::class, 'restore'])->name('account.restore');

    // Contacts
    Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts');
    Route::get('/contacts/create', [ContactsController::class, 'create'])->name('contacts.create');
    Route::post('/contacts', [ContactsController::class, 'store'])->name('contacts.store');
    Route::get('/contacts/{contact}/edit', [ContactsController::class, 'edit'])->name('contacts.edit');
    Route::put('/contacts/{contact}', [ContactsController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactsController::class, 'destroy'])->name('contacts.destroy');
    Route::put('/contacts/{contact}/restore', [ContactsController::class, 'restore'])->name('contacts.restore');

    // Users
    Route::get('/users', [UsersController::class, 'index'])->name('users');
    Route::get('/users/create', [UsersController::class, 'create'])->name('users.create');
    Route::post('/users', [UsersController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
    Route::put('/users/{user}/restore', [UsersController::class, 'restore'])->name('users.restore');

    // Reports
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
});