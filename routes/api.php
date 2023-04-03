<?php

use App\Http\Controllers\BotController;
use App\Http\Controllers\BotUsersController;
use App\Http\Controllers\WeatherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/webhook-telegram', [BotController::class, 'telegramWebhook']);
Route::post('/webhook-bale', [BotController::class, 'baleWebhook']);

Route::post('/webhook-telegram-users', [BotController::class, 'telegramUsersWebhook']);
Route::post('/webhook-bale-users', [BotController::class, 'baleUsersWebhook']);


Route::get('/approve', [BotUsersController::class, 'approve']);
