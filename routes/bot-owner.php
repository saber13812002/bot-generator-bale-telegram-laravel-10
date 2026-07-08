<?php

use App\Modules\BotOwner\Http\Controllers\BotAdminKieController;
use App\Modules\BotOwner\Http\Controllers\BotAdminPanelUserController;
use App\Modules\BotOwner\Http\Controllers\BotCategoryController;
use App\Modules\BotOwner\Http\Controllers\BotClaimController;
use App\Modules\BotOwner\Http\Controllers\BotItemsController;
use App\Modules\BotOwner\Http\Controllers\BotLibraryController;
use App\Modules\BotOwner\Http\Controllers\BotManageController;
use App\Modules\BotOwner\Http\Controllers\BotPlanController;
use App\Modules\BotOwner\Http\Controllers\BotSettingsController;
use App\Modules\BotOwner\Http\Controllers\BotUploadsController;
use App\Modules\BotOwner\Http\Controllers\CreateBotController;
use App\Modules\BotOwner\Http\Controllers\DashboardController;
use App\Modules\BotOwner\Http\Controllers\IntroController;
use App\Modules\BotOwner\Http\Controllers\LoginController;
use App\Modules\BotOwner\Http\Controllers\ProController;
use Illuminate\Support\Facades\Route;

Route::prefix('bots')->name('bot-owner.')->group(function () {
    Route::get('/', [IntroController::class, 'index'])->name('intro');
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/otp/send', [LoginController::class, 'sendOtp'])->name('otp.send')->middleware('throttle:10,1');
    Route::post('/otp/verify', [LoginController::class, 'verifyOtp'])->name('otp.verify')->middleware('throttle:20,1');

    Route::get('/create/{endpointId}', [CreateBotController::class, 'show'])->name('create');

    Route::middleware('bot-owner')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/pro/request', [ProController::class, 'request'])->name('pro.request');
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
        Route::post('/create/{endpointId}', [CreateBotController::class, 'store'])->name('create.store')->middleware('bot-owner.pro');

        // Bot Ownership Claim
        Route::get('/claim', [BotClaimController::class, 'index'])->name('claim');
        Route::post('/claim/generate', [BotClaimController::class, 'generate'])->name('claim.generate');
        Route::get('/claim/check-status', [BotClaimController::class, 'checkStatus'])->name('claim.check-status');

        // Bot Library & Management
        Route::get('/library', [BotLibraryController::class, 'index'])->name('library');
        Route::get('/manage/{bot}', [BotManageController::class, 'index'])->name('manage');
        Route::get('/manage/{bot}/stats', [BotManageController::class, 'stats'])->name('manage.stats');

        // Admin Panel Users (who can manage this bot in web panel)
        Route::get('/manage/{bot}/admin-panel-users', [BotAdminPanelUserController::class, 'index'])->name('manage.admin-panel-users');
        Route::post('/manage/{bot}/admin-panel-users', [BotAdminPanelUserController::class, 'store'])->name('manage.admin-panel-users.store');
        Route::delete('/manage/{bot}/admin-panel-users/{admin}', [BotAdminPanelUserController::class, 'destroy'])->name('manage.admin-panel-users.destroy');

        // Admin Kie (Admin Requests) Management
        Route::get('/manage/{bot}/admin-kie', [BotAdminKieController::class, 'index'])->name('manage.admin-kie');
        Route::post('/manage/{bot}/admin-kie/{request}/approve', [BotAdminKieController::class, 'approve'])->name('manage.admin-kie.approve');
        Route::post('/manage/{bot}/admin-kie/{request}/reject', [BotAdminKieController::class, 'reject'])->name('manage.admin-kie.reject');

        // Plan Requests Management
        Route::get('/manage/{bot}/plans', [BotPlanController::class, 'index'])->name('manage.plans');
        Route::post('/manage/{bot}/plans/{planRequest}/approve', [BotPlanController::class, 'approve'])->name('manage.plans.approve');
        Route::post('/manage/{bot}/plans/{planRequest}/reject', [BotPlanController::class, 'reject'])->name('manage.plans.reject');

        // Category Management
        Route::get('/manage/{bot}/categories', [BotCategoryController::class, 'index'])->name('manage.categories');
        Route::post('/manage/{bot}/categories', [BotCategoryController::class, 'store'])->name('manage.categories.store');
        Route::put('/manage/{bot}/categories/{category}', [BotCategoryController::class, 'update'])->name('manage.categories.update');
        Route::delete('/manage/{bot}/categories/{category}', [BotCategoryController::class, 'destroy'])->name('manage.categories.destroy');
        Route::post('/manage/{bot}/categories/reorder', [BotCategoryController::class, 'reorder'])->name('manage.categories.reorder');

        // Content Items Management
        Route::get('/manage/{bot}/items', [BotItemsController::class, 'index'])->name('manage.items');
        Route::post('/manage/{bot}/items/reorder', [BotItemsController::class, 'reorder'])->name('manage.items.reorder');

        // Pending Uploads Management
        Route::get('/manage/{bot}/uploads', [BotUploadsController::class, 'index'])->name('manage.uploads');
        Route::post('/manage/{bot}/uploads/{upload}/approve', [BotUploadsController::class, 'approve'])->name('manage.uploads.approve');
        Route::post('/manage/{bot}/uploads/{upload}/reject', [BotUploadsController::class, 'reject'])->name('manage.uploads.reject');

        // Bot-Specific Settings (type-based routing)
        Route::get('/manage/{bot}/settings', [BotSettingsController::class, 'index'])->name('manage.settings');
    });
});

// Bot Admin Panel User Search (JSON endpoint, no bot context needed)
Route::middleware('bot-owner')->prefix('bots')->name('bot-owner.')->group(function () {
    Route::get('/admin-panel-users/search', [BotAdminPanelUserController::class, 'search'])->name('admin-panel-users.search');
});
