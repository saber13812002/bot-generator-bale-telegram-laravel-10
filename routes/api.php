<?php

use App\Http\Controllers\AnalyzerController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BotMotherController;
use App\Http\Controllers\BotQuranAyatController;
use App\Http\Controllers\BotUsersController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\HadithSearchController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\NahjController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\QuranWordController;
use App\Http\Controllers\RssFeedWebOriginController;
use App\Http\Controllers\RssPostItemTranslationController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SocialPublishController;
use App\Http\Controllers\AudioBookController;
use App\Http\Controllers\MissionBotController;
use App\Http\Controllers\MissionMediaBotController;
use App\Http\Controllers\PersonnelAdminBotController;
use App\Http\Controllers\PersonnelRegistrationController;
use App\Http\Controllers\SongSaraPostController;
use App\Http\Controllers\TaskApprovalController;
use App\Http\Controllers\TestController;
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
Route::post('/gap', [QuranWordController::class, 'gap']);
Route::post('/webhook-quran-message-to-all', [QuranWordController::class, 'messageToAll']);

Route::post('/testReferral', [TestController::class, 'testReferral']);

Route::post('/webhook-rss', [BotMotherController::class, 'rss']);

Route::post('/webhook-blog', [BlogController::class, 'index']);
Route::get('/webhook-sms', [SmsController::class, 'index']);

Route::get('/scan', [ReportController::class, 'scan']);

Route::get('/daily-activity', [ReportController::class, 'dailyActivity']);
Route::get('/daily-search', [ReportController::class, 'dailySearch']);
Route::get('/daily-new-users', [ReportController::class, 'dailyNewUsers']);
Route::get('/daily-referral', [ReportController::class, 'dailyReferral']);
Route::get('/daily-recite', [ReportController::class, 'dailyRecite']);
Route::get('/daily-active-users', [ReportController::class, 'dailyActiveUsers']);

Route::get('/ayat/{id}', [BotQuranAyatController::class, 'ayat']);
Route::get('/search/{phrase}', [BotQuranAyatController::class, 'search']);
Route::get('/search2/{phrase}', [BotQuranAyatController::class, 'search2']);
Route::get('/search3/{phrase}', [BotQuranAyatController::class, 'search3']);
Route::post('/webhook-quran-ayat', [BotQuranAyatController::class, 'index']);

Route::get('/test/analyzer/{phrase}', [AnalyzerController::class, 'testAnalyzer']);

// hadith
Route::post('/webhook-hadith', [HadithSearchController::class, 'index']);

// nahj
Route::post('/webhook-nahj', [NahjController::class, 'index']);

Route::get('/job',[JobController::class, 'handle']);
// rss
Route::post('/webhook-rss', [RssPostItemTranslationController::class, 'index']);

Route::post('rss-generator',[RssFeedWebOriginController::class, 'store']);

Route::post('song-sara-songs',[SongSaraPostController::class, 'store']);

Route::get('chrome_extension_resend',[SocialPublishController::class, 'store']);

Route::get('/audiobooks/{audioBookId}', [AudioBookController::class, 'show']);

Route::get('calendar-data', [ContributionController::class, 'calendarData']);

// personnel registration
Route::post('/webhook-personnel-registration', [PersonnelRegistrationController::class, 'index']);
Route::post('/webhook-personnel-admin', [PersonnelAdminBotController::class, 'index']);

// mission bot
Route::post('/webhook-mission-bot', [MissionBotController::class, 'index']);
Route::post('/webhook-mission-media', [MissionMediaBotController::class, 'index']);
Route::post('/webhook-task-approval', [TaskApprovalController::class, 'index']);

// mission API routes
Route::prefix('missions')->group(function () {
    Route::get('/', [\App\Http\Controllers\MissionController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\MissionController::class, 'show']);
    Route::post('/{id}/request', [\App\Http\Controllers\MissionController::class, 'request']);
    Route::post('/{id}/cancel', [\App\Http\Controllers\MissionController::class, 'cancel']);
    Route::post('/{id}/submit', [\App\Http\Controllers\MissionController::class, 'submitResult']);
    Route::post('/cancel', [\App\Http\Controllers\MissionController::class, 'cancel']);
    Route::post('/submit', [\App\Http\Controllers\MissionController::class, 'submitResult']);
});

// API Token Management (protected routes - should be admin only)
Route::prefix('api-tokens')->middleware('api.token')->group(function () {
    Route::post('/generate', [\App\Http\Controllers\Api\ApiTokenController::class, 'generateToken']);
});

// Metadata API (no auth required for basic metadata)
Route::prefix('api/v1')->group(function () {
    Route::get('/metadata', [\App\Http\Controllers\Api\MetadataApiController::class, 'getMetadata']);
});

// Token test endpoint
Route::prefix('api/v1')->middleware('api.token')->group(function () {
    Route::get('/test-token', [\App\Http\Controllers\Api\MetadataApiController::class, 'testToken']);
});

// Mission API with Token Authentication
Route::prefix('api/v1/missions')->middleware('api.token')->group(function () {
    Route::post('/', [\App\Http\Controllers\Api\MissionApiController::class, 'createMission']);
    Route::get('/', [\App\Http\Controllers\Api\MissionApiController::class, 'listMissions']);
    Route::get('/{id}', [\App\Http\Controllers\Api\MissionApiController::class, 'getMission']);
    Route::get('/{id}/status', [\App\Http\Controllers\Api\MissionApiController::class, 'getMissionStatus']);
    Route::post('/{id}/content', [\App\Http\Controllers\Api\MissionApiController::class, 'addContent']);
    Route::post('/{id}/assign', [\App\Http\Controllers\Api\MissionApiController::class, 'assignMission']);
    Route::post('/{id}/submit', [\App\Http\Controllers\Api\MissionApiController::class, 'submitResult']);
});

// use App\Services\RssService;


// Route::post('/test-rss', function(Request $request, RssService $rssService) {
//     $rssId = $request->input('rss_id');
//     $uniqueField = $request->input('unique_field', 'link');
//     $rssUrl = $request->input('rss_url');

//     // اجرای تابع اصلی
//     $response = RssService::readRssAndSave($rssUrl, $rssId, $uniqueField);

//     // برگرداندن نتیجه (JSON)
//     return $response;
// });
