<?php

use App\Http\Controllers\BotController;
use App\Http\Controllers\BotUsersController;
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


Route::post('/webhook-telegram', [BotController::class, 'telegram']);
Route::post('/webhook-bale', [BotController::class, 'bale']);

Route::post('/webhook-telegram-users', [BotController::class, 'telegramUsers']);
Route::post('/webhook-bale-users', [BotController::class, 'baleUsers']);


Route::get('/approve', [BotUsersController::class, 'approve']);
