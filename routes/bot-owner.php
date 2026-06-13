<?php

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
    });
});
