<?php

use App\Http\Controllers\AccountAuthController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\ApplicationsController;
use App\Http\Controllers\ApplicationDocumentsController;
use App\Http\Controllers\ApplicationStatusController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProgressTrackerController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

// Account Authentication Routes
Route::get('/account/login', [AccountAuthController::class, 'showLoginForm'])->name('account.login');
Route::post('/account/login', [AccountAuthController::class, 'login']);
Route::delete('/account/logout', [AccountAuthController::class, 'logout'])->name('account.logout');

// User Authentication Routes
Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('login', [AuthenticatedSessionController::class, 'store']);
Route::delete('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Protected Routes (Both User and Account Guards)
Route::middleware(['auth:web,account'])->group(function () {
    
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Accounts Routes
    Route::prefix('accounts')->group(function () {
        Route::get('/', [AccountsController::class, 'index'])->name('accounts'); // No middleware - controller handles it
        Route::get('/create', [AccountsController::class, 'create'])->name('accounts.create')->middleware('role:admin');
        Route::post('/', [AccountsController::class, 'store'])->middleware('role:admin');
        Route::get('/{account}/edit', [AccountsController::class, 'edit'])->name('accounts.edit'); // No middleware - controller handles it
        Route::put('/{account}', [AccountsController::class, 'update']); // No middleware - controller handles it
        Route::delete('/{account}', [AccountsController::class, 'destroy'])->middleware('role:admin');
        Route::put('/{account}/restore', [AccountsController::class, 'restore'])->middleware('role:admin');
        
        // Account Email Actions
        Route::post('/{account}/send-credentials', [AccountsController::class, 'sendCredentialsEmail'])->middleware('role:admin');
        Route::post('/{account}/set-email-reminder', [AccountsController::class, 'setEmailReminder'])->middleware('role:admin');
        Route::post('/{account}/cancel-email-reminder', [AccountsController::class, 'cancelEmailReminder'])->middleware('role:admin');
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
        Route::post('/{application}/update-step', [ApplicationStatusController::class, 'updateStep']);
        Route::post('/{application}/send-contract', [ApplicationStatusController::class, 'sendContractLink']);
        Route::post('/{application}/send-approval-email', [ApplicationStatusController::class, 'sendApprovalEmail']);
        Route::post('/{application}/request-additional-info', [ApplicationStatusController::class, 'requestAdditionalInfo']);
        Route::post('/{application}/approve', [ApplicationStatusController::class, 'markAsApproved']);
        Route::get('/{application}/docusign-callback', [ApplicationStatusController::class, 'docusignCallback'])->name('applications.docusign-callback');
        
        // Application Email Reminders
        Route::post('/{application}/set-email-reminder', [ApplicationsController::class, 'setEmailReminder']);
        Route::post('/{application}/cancel-email-reminder', [ApplicationsController::class, 'cancelEmailReminder']);

        // Document Upload
        Route::post('/{application}/documents', [ApplicationDocumentsController::class, 'store'])
            ->name('applications.documents.store');
        Route::get('/{application}/documents/{document}/download', [ApplicationDocumentsController::class, 'download'])
            ->name('applications.documents.download');
        Route::delete('/{application}/documents/{document}', [ApplicationDocumentsController::class, 'destroy'])
            ->name('applications.documents.destroy');
    });

    // Progress Tracker
    Route::get('/progress-tracker', [ProgressTrackerController::class, 'index'])->name('progress-tracker');

    // Users Routes (Admin Only)
    Route::middleware('role:admin')->prefix('users')->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('users');
        Route::get('/create', [UsersController::class, 'create'])->name('users.create');
        Route::post('/', [UsersController::class, 'store']);
        Route::get('/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
        Route::put('/{user}', [UsersController::class, 'update']);
        Route::delete('/{user}', [UsersController::class, 'destroy']);
        Route::put('/{user}/restore', [UsersController::class, 'restore']);
    });

    // Settings Routes (Admin Only)
    Route::middleware('role:admin')->prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('settings');
        Route::post('/users/{user}/toggle-admin', [SettingsController::class, 'toggleUserAdmin']);
        Route::post('/users/{user}/permissions', [SettingsController::class, 'updateUserPermissions']);
    });
});
