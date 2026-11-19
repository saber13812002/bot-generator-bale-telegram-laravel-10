<?php

use App\Http\Controllers\ContributionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\RssController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [\App\Http\Controllers\WelcomeController::class, 'index']);

// صفحه جزئیات ربات
Route::get('/bot/{endpointId}', [\App\Http\Controllers\WelcomeController::class, 'show'])
    ->name('bot.show');

Route::get('/approve', function () {
    return view('approve');
});

Route::get('/report', function (Request $request) {
    // TODO: Generate graph

    $chatId = $request->input('chat_id');
    $origin = $request->input('origin');
    $language = $request->input('language');
    return view('report', ['chat_id' => $chatId, 'origin' => $origin, 'language' => $language]);
});



Route::get('/rss/evand', [RssController::class, 'generateRSS']);
Route::get('/rss/audiobook', [RssController::class, 'audiobook']);
Route::get('/rss/gitir', [RssController::class, 'gitir']);
Route::get('/fetch-courses', [CourseController::class, 'fetchCourses']);


//contributions ContributionController

Route::get('/contributions', [ContributionController::class, 'design']);
//Route::get('/calendar', [ContributionController::class, 'calendar']);

// Prayer Report Web Pages
Route::get('/namaz-ghaza/{token}', [\App\Http\Controllers\PrayerReportWebController::class, 'show']);
Route::get('/test-js', [\App\Http\Controllers\PrayerReportWebController::class, 'test']);

// Pro Purchase Approval Routes
Route::get('/admin/pro-purchase/{id}/approve', [\App\Http\Controllers\Admin\ProPurchaseController::class, 'approve'])
    ->name('admin.pro-purchase.approve');
Route::get('/admin/pro-purchase/{id}/reject', [\App\Http\Controllers\Admin\ProPurchaseController::class, 'reject'])
    ->name('admin.pro-purchase.reject');

// Bot Owner Pro Approval Routes
Route::get('/admin/bot-owner-pro/{id}/approve', [\App\Http\Controllers\Admin\BotOwnerProController::class, 'approve'])
    ->name('admin.bot-owner-pro.approve');
Route::get('/admin/bot-owner-pro/{id}/reject', [\App\Http\Controllers\Admin\BotOwnerProController::class, 'reject'])
    ->name('admin.bot-owner-pro.reject');

// Idea / Ticket System
Route::prefix('idea')->name('idea.')->group(function () {
    Route::get('/', [\App\Http\Controllers\IdeaController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\IdeaController::class, 'store'])->name('store');
    Route::get('/login', [\App\Http\Controllers\IdeaController::class, 'login'])->name('login');
    Route::post('/otp-send', [\App\Http\Controllers\IdeaController::class, 'sendOtp'])->name('otp.send');
    Route::post('/otp-verify', [\App\Http\Controllers\IdeaController::class, 'verifyOtp'])->name('otp.verify');
    Route::get('/dashboard', [\App\Http\Controllers\IdeaController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [\App\Http\Controllers\IdeaController::class, 'logout'])->name('logout');
    Route::get('/{trackingCode}', [\App\Http\Controllers\IdeaController::class, 'show'])->name('show');
    Route::post('/{trackingCode}/message', [\App\Http\Controllers\IdeaController::class, 'message'])->name('message');
    Route::get('/{trackingCode}/verify-email', [\App\Http\Controllers\IdeaController::class, 'verifyEmail'])->name('verify.email');
});
