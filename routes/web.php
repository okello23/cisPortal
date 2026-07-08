<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManagerListController;
use App\Http\Controllers\PublicDashboardController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketFeedbackController;
use App\Http\Controllers\TicketTrackingController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicDashboardController::class, 'home'])->name('home');

Route::get('/report', [PublicTicketController::class, 'create'])->name('tickets.create');
Route::post('/report', [PublicTicketController::class, 'store'])->name('tickets.store');

Route::get('/track', [TicketTrackingController::class, 'create'])->name('tickets.track');
Route::post('/track', [TicketTrackingController::class, 'search'])->name('tickets.track.search');
Route::get('/attachments/{ticket}', [TicketAttachmentController::class, 'show'])->middleware('signed')->name('tickets.attachments.show');
Route::get('/track/{ticket}/feedback', [TicketFeedbackController::class, 'create'])->middleware('signed')->name('tickets.feedback.show');
Route::post('/track/{ticket}/feedback', [TicketFeedbackController::class, 'store'])->middleware('signed')->name('tickets.feedback.store');

Route::get('/dashboard/public', [PublicDashboardController::class, 'index'])->name('dashboard.public');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/support-performance/export/{format}', [AdminDashboardController::class, 'exportPerformance'])->name('dashboard.support-performance.export');
    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets.index');
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('admin.tickets.show');
    Route::get('/tickets/{ticket}/audit-trail', [AdminTicketController::class, 'auditTrail'])->name('admin.tickets.audit-trail');
    Route::put('/tickets/{ticket}', [AdminTicketController::class, 'update'])->name('admin.tickets.update');
    Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('admin.users.store');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::get('/lists/{list}', [ManagerListController::class, 'index'])->name('lists.index');
    Route::post('/lists/{list}', [ManagerListController::class, 'store'])->name('lists.store');
    Route::put('/lists/{list}/{id}', [ManagerListController::class, 'update'])->name('lists.update');
    Route::post('/lists/facilities/sync', [ManagerListController::class, 'syncFacilities'])->name('lists.facilities.sync');
});
