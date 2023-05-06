<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BotUsersController;
use App\Http\Controllers\QuranWordController;
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

Route::post('/webhook-bot-mother', [BotController::class, 'botMotherWebhook']);
Route::post('/webhook-bot-get-id', [BotController::class, 'getId']);

Route::post('/webhook-bot-children', [BotController::class, 'childrenWebhook']);
Route::get('/approve', [BotUsersController::class, 'approve']);

Route::post('/webhook-weather', [WeatherController::class, 'index']);

Route::post('/webhook-quran-word', [QuranWordController::class, 'index']);
Route::post('/webhook-quran-message-to-all', [QuranWordController::class, 'messageToAll']);

Route::post('/webhook-rss', [BotController::class, 'rss']);

Route::post('/webhook-blog', [BlogController::class, 'index']);
