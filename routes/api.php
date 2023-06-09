<?php

use App\Http\Controllers\AnalyzerController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BotMotherController;
use App\Http\Controllers\BotQuranAyatController;
use App\Http\Controllers\BotUsersController;
use App\Http\Controllers\QuranWordController;
use App\Http\Controllers\SmsController;
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

Route::post('/webhook-bot-mother', [BotMotherController::class, 'botMotherWebhook']);
Route::post('/webhook-bot-get-id', [BotMotherController::class, 'getIdMother']);

Route::post('/webhook-bot-children', [BotMotherController::class, 'childrenMessageBroadcasterWebhook']);
Route::get('/approve', [BotUsersController::class, 'approve']);

Route::post('/webhook-weather', [WeatherController::class, 'index']);

Route::post('/webhook-quran-word', [QuranWordController::class, 'index']);
Route::post('/webhook-quran-message-to-all', [QuranWordController::class, 'messageToAll']);

Route::post('/webhook-rss', [BotMotherController::class, 'rss']);

Route::post('/webhook-blog', [BlogController::class, 'index']);
Route::get('/webhook-sms', [SmsController::class, 'index']);


Route::get('/ayat/{id}', [BotQuranAyatController::class, 'ayat']);
Route::get('/search/{phrase}', [BotQuranAyatController::class, 'search']);
Route::get('/search2/{phrase}', [BotQuranAyatController::class, 'search2']);
Route::get('/search3/{phrase}', [BotQuranAyatController::class, 'search3']);
Route::post('/webhook-quran-ayat', [BotQuranAyatController::class, 'index']);

Route::get('/test/analyzer/{phrase}', [AnalyzerController::class, 'testAnalyzer']);
