<?php

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

Route::get('/', function () {
    return view('welcome');
});

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
