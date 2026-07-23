<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Infrastructure\AlisRemoteBackupKeyController;
use App\Http\Controllers\ManagerListController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PublicSubmissionAdminController;
use App\Http\Controllers\PublicDashboardController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketFeedbackController;
use App\Http\Controllers\TicketTrackingController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\EnsurePasswordIsCurrent;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicDashboardController::class, 'home'])->name('home');

Route::get('/report', [PublicTicketController::class, 'create'])->name('tickets.create');
Route::post('/report', [PublicTicketController::class, 'store'])->name('tickets.store');

Route::get('/track', [TicketTrackingController::class, 'create'])->name('tickets.track');
Route::post('/track', [TicketTrackingController::class, 'search'])->name('tickets.track.search');
Route::get('/track/{ticket}/incident-resolution-report', [TicketTrackingController::class, 'downloadIncidentResolutionReport'])->middleware('signed')->name('tickets.report.download');
Route::get('/attachments/{attachment}', [TicketAttachmentController::class, 'show'])->middleware('signed:relative')->name('tickets.attachments.show');
Route::get('/attachments/{attachment}/preview', [TicketAttachmentController::class, 'preview'])->middleware('signed:relative')->name('tickets.attachments.preview');
Route::get('/track/{ticket}/feedback', [TicketFeedbackController::class, 'create'])->middleware('signed:relative')->name('tickets.feedback.show');
Route::post('/track/{ticket}/feedback', [TicketFeedbackController::class, 'store'])->middleware('signed:relative')->name('tickets.feedback.store');

Route::get('/dashboard/public', [PublicDashboardController::class, 'index'])->name('dashboard.public');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordController::class, 'createForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'storeForgotPassword'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'createResetPassword'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'storeResetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/password/change', [PasswordController::class, 'editCurrentPassword'])->name('password.change.edit');
    Route::put('/password/change', [PasswordController::class, 'updateCurrentPassword'])->name('password.change.update');

    Route::middleware(EnsurePasswordIsCurrent::class)->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/support-performance/export/{format}', [AdminDashboardController::class, 'exportPerformance'])->name('dashboard.support-performance.export');
        Route::get('/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets.index');
        Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('admin.tickets.show');
        Route::get('/tickets/{ticket}/incident-resolution-report', [AdminTicketController::class, 'downloadIncidentResolutionReport'])->name('admin.tickets.incident-resolution-report');
        Route::get('/tickets/{ticket}/audit-trail', [AdminTicketController::class, 'auditTrail'])->name('admin.tickets.audit-trail');
        Route::put('/tickets/{ticket}', [AdminTicketController::class, 'update'])->name('admin.tickets.update');
        Route::get('/anti-spam', [PublicSubmissionAdminController::class, 'dashboard'])->name('admin.anti-spam.dashboard');
        Route::get('/anti-spam/quarantine', [PublicSubmissionAdminController::class, 'quarantine'])->name('admin.anti-spam.quarantine');
        Route::put('/anti-spam/quarantine/{ticket}', [PublicSubmissionAdminController::class, 'updateQuarantine'])->name('admin.anti-spam.quarantine.update');
        Route::get('/anti-spam/events', [PublicSubmissionAdminController::class, 'events'])->name('admin.anti-spam.events');
        Route::post('/anti-spam/blocks', [PublicSubmissionAdminController::class, 'storeBlock'])->name('admin.anti-spam.blocks.store');
        Route::delete('/anti-spam/blocks/{block}', [PublicSubmissionAdminController::class, 'destroyBlock'])->name('admin.anti-spam.blocks.destroy');
        Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('admin.users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
        Route::get('/infrastructure/alis-remote-backup-keys', [AlisRemoteBackupKeyController::class, 'index'])->name('infrastructure.alis-remote-backup-keys.index');
        Route::get('/infrastructure/alis-remote-backup-keys/{configuration}/download-script', [AlisRemoteBackupKeyController::class, 'downloadScript'])->name('infrastructure.alis-remote-backup-keys.download-script');
        Route::get('/lists/{list}', [ManagerListController::class, 'index'])->name('lists.index');
        Route::post('/lists/{list}', [ManagerListController::class, 'store'])->name('lists.store');
        Route::put('/lists/{list}/{id}', [ManagerListController::class, 'update'])->name('lists.update');
        Route::post('/lists/facilities/sync', [ManagerListController::class, 'syncFacilities'])->name('lists.facilities.sync');
    });
});
