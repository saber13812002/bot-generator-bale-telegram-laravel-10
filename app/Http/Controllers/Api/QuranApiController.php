<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QuranHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Quran\GetAyahRequest;
use App\Http\Requests\Api\Quran\SearchRequest;
use App\Http\Requests\Api\Quran\UpdateTranslationRequest;
use App\Http\Requests\Api\Quran\UpdateUserSettingsRequest;
use App\Http\Resources\Api\Quran\AyahResource;
use App\Http\Resources\Api\Quran\SurahResource;
use App\Http\Resources\Api\Quran\TranslationResource;
use App\Http\Resources\Api\Quran\TrendingAyahResource;
use App\Http\Resources\Api\Quran\UserSettingsResource;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\QuranAyat;
use App\Models\QuranSurah;
use App\Models\QuranTranslation;
use App\Models\QuranTransliterationEn;
use App\Models\QuranTransliterationTr;
use App\Models\QuranWord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Saber13812002\Laravel\Fulltext\IndexedRecord;
use Saber13812002\Laravel\Fulltext\Search;

/**
 * @OA\Tag(
 *     name="Quran",
 *     description="API endpoints for Quran content, translations, and user settings"
 * )
 */
class QuranApiController extends Controller
{
    private QuranBotUserRankingService $quranBotUserRankingService;

    public function __construct(QuranBotUserRankingService $quranBotUserRankingService)
    {
        $this->quranBotUserRankingService = $quranBotUserRankingService;
    }

    /**
     * Normalize language code for database queries
     * Converts codes like ar-IQ -> ar, de-DE -> de, zh-CN -> zh
     */
    private static function normalizeLanguageCodeForDatabase(string $languageCode): string
    {
        if (strpos($languageCode, '-') !== false) {
            return explode('-', $languageCode)[0];
        }
        return $languageCode;
    }

    /**
     * Get user by chat_id or user_id
     */
    private function getUser(Request $request): ?BotUsers
    {
        $chatId = $request->input('chat_id');
        $userId = $request->input('user_id');
        $botMotherId = $request->input('bot_mother_id', 1);
        $origin = $request->input('origin', 'telegram');

        if ($chatId) {
            return BotUsers::where('chat_id', $chatId)
                ->where('bot_id', $botMotherId)
                ->where('origin', $origin)
                ->first();
        }

        if ($userId) {
            // For mobile app, we might use user_id differently
            // For now, treat user_id as chat_id
            return BotUsers::where('chat_id', $userId)
                ->where('bot_id', $botMotherId)
                ->where('origin', $origin)
                ->first();
        }

        return null;
    }

    /**
     * Get list of available languages
     * 
     * @OA\Get(
     *     path="/api/v1/quran/languages",
     *     summary="Get list of available languages",
     *     tags={"Quran"},
     *     @OA\Response(
     *         response=200,
     *         description="List of available languages",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="string", example="fa")
     *             )
     *         )
     *     )
     * )
     */
    public function getLanguages(Request $request): JsonResponse
    {
        try {
            $languages = QuranTranslation::query()
                ->select('language')
                ->distinct()
                ->orderBy('language')
                ->pluck('language')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $languages
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting languages', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get languages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/translations",
     *     summary="Get list of translations for a language",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         required=true,
     *         description="Language code (e.g., fa, en, ar-IQ)",
     *         @OA\Schema(type="string", example="fa")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of translations",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TranslationResponse")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Language parameter is required"
     *     )
     * )
     */
    public function getTranslations(Request $request): JsonResponse
    {
        try {
            $language = $request->input('language');
            if (!$language) {
                return response()->json([
                    'success' => false,
                    'message' => 'Language parameter is required'
                ], 400);
            }

            $normalizedLanguage = self::normalizeLanguageCodeForDatabase($language);

            $translations = QuranTranslation::query()
                ->where(function($query) use ($language, $normalizedLanguage) {
                    $query->where('language', $language);
                    if ($normalizedLanguage != $language) {
                        $query->orWhere('language', $normalizedLanguage);
                    }
                })
                ->select('translator_name', 'translate_full_name', 'language')
                ->distinct()
                ->orderBy('translator_name')
                ->get();

            $service = new \App\Services\QuranTranslationImportService();
            $translationsWithCompleteness = $translations->map(function($translation) use ($service, $language, $normalizedLanguage) {
                $completeness = $service->checkTranslationCompleteness(
                    $translation->language,
                    $translation->translator_name
                );
                return [
                    'language' => $translation->language,
                    'translator_name' => $translation->translator_name,
                    'translate_full_name' => $translation->translate_full_name,
                    'is_complete' => $completeness['is_complete'],
                    'completeness_percentage' => $completeness['percentage'],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => TranslationResource::collection($translationsWithCompleteness)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting translations', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get translations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/surahs",
     *     summary="Get list of 114 Surahs",
     *     tags={"Quran"},
     *     @OA\Response(
     *         response=200,
     *         description="List of Surahs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/SurahResponse")
     *             )
     *         )
     *     )
     * )
     */
    public function getSurahs(Request $request): JsonResponse
    {
        try {
            $surahs = QuranSurah::select(['id', 'arabic', 'name', 'ayah', 'meccamedinan', 'sortnozol'])
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => SurahResource::collection($surahs)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting surahs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get surahs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/surahs/{sura}/ayahs/{ayah}",
     *     summary="Get a specific Ayah with translation",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="sura",
     *         in="path",
     *         required=true,
     *         description="Surah number (1-114)",
     *         @OA\Schema(type="integer", example=1, minimum=1, maximum=114)
     *     ),
     *     @OA\Parameter(
     *         name="ayah",
     *         in="path",
     *         required=true,
     *         description="Ayah number",
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         required=false,
     *         description="Translation language code",
     *         @OA\Schema(type="string", example="fa")
     *     ),
     *     @OA\Parameter(
     *         name="translator",
     *         in="query",
     *         required=false,
     *         description="Translator name",
     *         @OA\Schema(type="string", example="ansarian")
     *     ),
     *     @OA\Parameter(
     *         name="chat_id",
     *         in="query",
     *         required=false,
     *         description="User chat_id for personalized settings",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="User ID for mobile app",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ayah data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/AyahResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ayah not found"
     *     )
     * )
     */
    public function getAyah(int $sura, int $ayah, GetAyahRequest $request): JsonResponse
    {
        try {
            // Get user settings if chat_id or user_id provided
            $userSettings = $this->getUser($request);
            
            // Get language and translator
            $language = $request->input('language');
            if (!$language && $userSettings) {
                $language = $userSettings->setting('quran_translation_language') ?? App::getLocale();
            }
            if (!$language) {
                $language = App::getLocale() ?: 'fa';
            }

            $translator = $request->input('translator');
            if (!$translator && $userSettings) {
                $translator = $userSettings->setting('quran_translation_translator');
            }

            // Get Arabic text
            $quranWords = QuranWord::query()
                ->where('sura', $sura)
                ->where('aya', $ayah)
                ->get();

            if ($quranWords->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ayah not found'
                ], 404);
            }

            $arabicText = '';
            $pageNumber = 0;
            foreach ($quranWords as $word) {
                $arabicText .= ' ' . $word->text;
                $pageNumber = $word->page;
            }

            // Get translation
            $quranTranslate = QuranHelper::getQuranTranslation($language, $translator, $sura, $ayah, $userSettings);
            
            if (!$quranTranslate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Translation not found for this language and translator'
                ], 404);
            }

            // Get transliteration if enabled
            $transliterationTr = null;
            $transliterationEn = null;
            if ($userSettings) {
                $trEnabled = $userSettings->setting('quran_transliteration_tr') == 'true';
                $enEnabled = $userSettings->setting('quran_transliteration_en') == 'true';
                
                if ($trEnabled && $quranTranslate->index) {
                    $trData = QuranTransliterationTr::query()
                        ->where('index', $quranTranslate->index)
                        ->first();
                    $transliterationTr = $trData->quran_transliteration_tr ?? null;
                }
                
                if ($enEnabled && $quranTranslate->index) {
                    $enData = QuranTransliterationEn::query()
                        ->where('index', $quranTranslate->index)
                        ->first();
                    $transliterationEn = $enData->quran_transliteration_en ?? null;
                }
            }

            // Get surah info
            [$maxAyah, $suraName] = QuranHelper::getLastAyeBySurehId($sura);
            $surah = QuranSurah::find($sura);

            // Calculate next/previous ayah
            $nextAyah = null;
            $previousAyah = null;
            if ($ayah < $maxAyah) {
                $nextAyah = ['sura' => $sura, 'ayah' => $ayah + 1];
            } elseif ($sura < 114) {
                $nextAyah = ['sura' => $sura + 1, 'ayah' => 1];
            }
            
            if ($ayah > 1) {
                $previousAyah = ['sura' => $sura, 'ayah' => $ayah - 1];
            } elseif ($sura > 1) {
                $prevSurah = QuranSurah::find($sura - 1);
                $previousAyah = ['sura' => $sura - 1, 'ayah' => $prevSurah->ayah ?? 1];
            }

            $ayahData = (object)[
                'id' => $quranTranslate->index ?? null,
                'sura' => $sura,
                'aya' => $ayah,
                'arabic_text' => trim($arabicText),
                'simple_text' => null,
                'translation' => $quranTranslate->text,
                'translation_language' => $quranTranslate->language,
                'translation_translator' => $quranTranslate->translator_name,
                'transliteration_tr' => $transliterationTr,
                'transliteration_en' => $transliterationEn,
                'page' => $pageNumber,
                'juz' => $quranWords->first()->juz ?? null,
                'hezb' => $quranWords->first()->hezb ?? null,
                'sura_name' => $surah->name ?? null,
                'sura_name_arabic' => $suraName,
                'next_ayah' => $nextAyah,
                'previous_ayah' => $previousAyah,
            ];

            return response()->json([
                'success' => true,
                'data' => new AyahResource($ayahData)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting ayah', [
                'error' => $e->getMessage(),
                'sura' => $sura,
                'ayah' => $ayah
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get ayah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/words/{wordId}",
     *     summary="Get word by word Quran text",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="wordId",
     *         in="path",
     *         required=true,
     *         description="Word ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Word data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="word_id", type="integer"),
     *                 @OA\Property(property="text", type="string"),
     *                 @OA\Property(property="is_end_aya", type="boolean"),
     *                 @OA\Property(property="next_word_id", type="integer"),
     *                 @OA\Property(property="previous_word_id", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Word not found"
     *     )
     * )
     */
    public function getWord(int $wordId, Request $request): JsonResponse
    {
        try {
            [$word, $isEndAya] = QuranHelper::getQuranWordById($wordId);
            
            if ($word === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Word not found'
                ], 404);
            }

            $nextWordId = $wordId == 88246 ? 88246 : $wordId + 1;
            $previousWordId = $wordId == 1 ? 1 : $wordId - 1;

            return response()->json([
                'success' => true,
                'data' => [
                    'word_id' => $wordId,
                    'text' => $word,
                    'is_end_aya' => $isEndAya == 1,
                    'next_word_id' => $nextWordId,
                    'previous_word_id' => $previousWordId,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting word', ['error' => $e->getMessage(), 'word_id' => $wordId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get word',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/juz",
     *     summary="Get list of 30 Juz",
     *     tags={"Quran"},
     *     @OA\Response(
     *         response=200,
     *         description="List of Juz",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="juz", type="integer", example=1),
     *                     @OA\Property(property="command", type="string", example="/sure1ayah1")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getJuz(Request $request): JsonResponse
    {
        try {
            $juzList = [];
            for ($i = 1; $i <= 30; $i++) {
                $juzList[] = [
                    'juz' => $i,
                    'command' => config('juz.' . $i),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $juzList
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting juz list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get juz list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/juz/{juz}",
     *     summary="Get content of a specific Juz",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="juz",
     *         in="path",
     *         required=true,
     *         description="Juz number (1-30)",
     *         @OA\Schema(type="integer", example=1, minimum=1, maximum=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Juz content",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getJuzContent(int $juz, Request $request): JsonResponse
    {
        try {
            if ($juz < 1 || $juz > 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Juz number must be between 1 and 30'
                ], 400);
            }

            $command = config('juz.' . $juz);
            // Parse command to get sura and ayah
            if (preg_match('/\/sure(\d+)ayah(\d+)/', $command, $matches)) {
                $startSura = (int)$matches[1];
                $startAyah = (int)$matches[2];
                
                // Get end sura and ayah for next juz
                $endSura = $startSura;
                $endAyah = $startAyah;
                if ($juz < 30) {
                    $nextCommand = config('juz.' . ($juz + 1));
                    if (preg_match('/\/sure(\d+)ayah(\d+)/', $nextCommand, $nextMatches)) {
                        $endSura = (int)$nextMatches[1];
                        $endAyah = (int)$nextMatches[2] - 1;
                    }
                } else {
                    // Last juz ends at sura 114, ayah 6
                    $endSura = 114;
                    $endAyah = 6;
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'juz' => $juz,
                        'start' => [
                            'sura' => $startSura,
                            'ayah' => $startAyah,
                        ],
                        'end' => [
                            'sura' => $endSura,
                            'ayah' => $endAyah,
                        ],
                        'command' => $command,
                    ]
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid juz command format'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error getting juz content', ['error' => $e->getMessage(), 'juz' => $juz]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get juz content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/search",
     *     summary="Search in Quran text and translations",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=true,
     *         description="Search phrase",
     *         @OA\Schema(type="string", example="الرحمن")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Results per page",
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="results", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function search(SearchRequest $request): JsonResponse
    {
        try {
            $searchPhrase = $request->input('query');
            $pageNumber = $request->input('page', 1);
            $perPage = $request->input('per_page', 10);

            $normalizedPhrase = IndexedRecord::normalize($searchPhrase);
            $results = QuranHelper::getResultSearch($normalizedPhrase, $pageNumber);

            // Convert to array format
            $formattedResults = [];
            foreach ($results as $item) {
                $ayah = $item->indexable;
                $formattedResults[] = [
                    'id' => $ayah->id ?? null,
                    'sura' => $ayah->sura,
                    'aya' => $ayah->aya,
                    'text' => $ayah->text,
                    'simple' => $ayah->simple,
                    'highlighted_text' => $item->indexed_title ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $formattedResults,
                    'current_page' => $pageNumber,
                    'per_page' => $perPage,
                    'total' => count($formattedResults),
                    'query' => $searchPhrase,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error searching Quran', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to search',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/user/settings",
     *     summary="Get user settings",
     *     tags={"Quran"},
     *     security={{"BearerAuth":{}}, {"ApiTokenAuth":{}}},
     *     @OA\Parameter(
     *         name="chat_id",
     *         in="query",
     *         required=false,
     *         description="User chat_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="User ID for mobile app",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User settings",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/UserSettingsResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function getUserSettings(Request $request): JsonResponse
    {
        try {
            $userSettings = $this->getUser($request);
            
            if (!$userSettings) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $settings = (object)[
                'mp3_enable' => $userSettings->setting('mp3_enable') == 'true',
                'mp3_reciter' => $userSettings->setting('mp3_reciter') ?? 'parhizgar',
                'quran_translation_language' => $userSettings->setting('quran_translation_language'),
                'quran_translation_translator' => $userSettings->setting('quran_translation_translator'),
                'quran_transliteration_tr' => $userSettings->setting('quran_transliteration_tr') == 'true',
                'quran_transliteration_en' => $userSettings->setting('quran_transliteration_en') == 'true',
                'placequran_enable' => $userSettings->setting('placequran_enable') == 'true',
            ];

            return response()->json([
                'success' => true,
                'data' => new UserSettingsResource($settings)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting user settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quran/user/settings",
     *     summary="Update user settings",
     *     tags={"Quran"},
     *     security={{"BearerAuth":{}}, {"ApiTokenAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="chat_id", type="string", description="User chat_id"),
     *             @OA\Property(property="user_id", type="string", description="User ID for mobile app"),
     *             @OA\Property(property="mp3_enable", type="boolean"),
     *             @OA\Property(property="mp3_reciter", type="string", example="parhizgar"),
     *             @OA\Property(property="quran_translation_language", type="string", example="fa"),
     *             @OA\Property(property="quran_translation_translator", type="string", example="ansarian"),
     *             @OA\Property(property="quran_transliteration_tr", type="boolean"),
     *             @OA\Property(property="quran_transliteration_en", type="boolean"),
     *             @OA\Property(property="placequran_enable", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Settings updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function updateUserSettings(UpdateUserSettingsRequest $request): JsonResponse
    {
        try {
            $userSettings = $this->getUser($request);
            
            if (!$userSettings) {
                // Create new user if doesn't exist
                $chatId = $request->input('chat_id') ?? $request->input('user_id');
                $botMotherId = $request->input('bot_mother_id', 1);
                $origin = $request->input('origin', 'telegram');
                
                if (!$chatId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'chat_id or user_id is required'
                    ], 400);
                }

                $userSettings = BotUsers::firstOrNew($chatId, $botMotherId, $origin);
            }

            $updateData = [];
            
            if ($request->has('mp3_enable')) {
                $updateData['mp3_enable'] = $request->input('mp3_enable') ? 'true' : 'false';
            }
            if ($request->has('mp3_reciter')) {
                $updateData['mp3_reciter'] = $request->input('mp3_reciter');
            }
            if ($request->has('quran_translation_language')) {
                $updateData['quran_translation_language'] = $request->input('quran_translation_language');
            }
            if ($request->has('quran_translation_translator')) {
                $updateData['quran_translation_translator'] = $request->input('quran_translation_translator');
            }
            if ($request->has('quran_transliteration_tr')) {
                $updateData['quran_transliteration_tr'] = $request->input('quran_transliteration_tr') ? 'true' : 'false';
            }
            if ($request->has('quran_transliteration_en')) {
                $updateData['quran_transliteration_en'] = $request->input('quran_transliteration_en') ? 'true' : 'false';
            }
            if ($request->has('placequran_enable')) {
                $updateData['placequran_enable'] = $request->input('placequran_enable') ? 'true' : 'false';
            }

            // Get existing settings and merge
            $existingSettings = [
                'mp3_reciter' => $userSettings->setting('mp3_reciter'),
                'mp3_enable' => $userSettings->setting('mp3_enable'),
                'quran_transliteration_tr' => $userSettings->setting('quran_transliteration_tr'),
                'quran_transliteration_en' => $userSettings->setting('quran_transliteration_en'),
                'translation_id' => $userSettings->setting('translation_id'),
                'quran_translation_language' => $userSettings->setting('quran_translation_language'),
                'quran_translation_translator' => $userSettings->setting('quran_translation_translator'),
            ];

            $mergedSettings = array_merge($existingSettings, $updateData);
            $userSettings->settings($mergedSettings);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => new UserSettingsResource((object)$mergedSettings)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating user settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quran/user/settings/translation",
     *     summary="Update user translation settings",
     *     tags={"Quran"},
     *     security={{"BearerAuth":{}}, {"ApiTokenAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"language", "translator"},
     *             @OA\Property(property="chat_id", type="string"),
     *             @OA\Property(property="user_id", type="string"),
     *             @OA\Property(property="language", type="string", example="fa"),
     *             @OA\Property(property="translator", type="string", example="ansarian")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translation updated successfully"
     *     )
     * )
     */
    public function updateTranslation(UpdateTranslationRequest $request): JsonResponse
    {
        try {
            $userSettings = $this->getUser($request);
            
            if (!$userSettings) {
                $chatId = $request->input('chat_id') ?? $request->input('user_id');
                $botMotherId = $request->input('bot_mother_id', 1);
                $origin = $request->input('origin', 'telegram');
                
                if (!$chatId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'chat_id or user_id is required'
                    ], 400);
                }

                $userSettings = BotUsers::firstOrNew($chatId, $botMotherId, $origin);
            }

            $language = $request->input('language');
            $translator = $request->input('translator');

            // Get existing settings
            $existingSettings = [
                'mp3_reciter' => $userSettings->setting('mp3_reciter'),
                'mp3_enable' => $userSettings->setting('mp3_enable'),
                'quran_transliteration_tr' => $userSettings->setting('quran_transliteration_tr'),
                'quran_transliteration_en' => $userSettings->setting('quran_transliteration_en'),
                'translation_id' => $userSettings->setting('translation_id'),
            ];

            $updateData = array_merge($existingSettings, [
                'quran_translation_language' => $language,
                'quran_translation_translator' => $translator,
            ]);

            $userSettings->settings($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Translation updated successfully',
                'data' => [
                    'language' => $language,
                    'translator' => $translator,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating translation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update translation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/user/report",
     *     summary="Get user activity report",
     *     tags={"Quran"},
     *     security={{"BearerAuth":{}}, {"ApiTokenAuth":{}}},
     *     @OA\Parameter(
     *         name="chat_id",
     *         in="query",
     *         required=false,
     *         description="User chat_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="User ID for mobile app",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getUserReport(Request $request): JsonResponse
    {
        try {
            $userSettings = $this->getUser($request);
            
            if (!$userSettings) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $chatId = $request->input('chat_id') ?? $request->input('user_id') ?? $userSettings->chat_id;
            
            // Get report data (we'll need to adapt the service method for API)
            $reportUrl = url('/report?chat_id=' . $chatId . '&language=' . ($request->input('language') ?? 'fa') . '&origin=' . ($request->input('origin') ?? 'telegram'));

            return response()->json([
                'success' => true,
                'data' => [
                    'chat_id' => $chatId,
                    'report_url' => $reportUrl,
                    'message' => 'Report generated successfully. Visit the URL to view your report.',
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting user report', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/user/referral-stats",
     *     summary="Get user referral statistics",
     *     tags={"Quran"},
     *     security={{"BearerAuth":{}}, {"ApiTokenAuth":{}}},
     *     @OA\Parameter(
     *         name="chat_id",
     *         in="query",
     *         required=false,
     *         description="User chat_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="User ID for mobile app",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referral statistics",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getReferralStats(Request $request): JsonResponse
    {
        try {
            $userSettings = $this->getUser($request);
            
            if (!$userSettings) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $chatId = $request->input('chat_id') ?? $request->input('user_id') ?? $userSettings->chat_id;
            $stats = $this->quranBotUserRankingService->getReferralStatistics($chatId);

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting referral stats', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get referral stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/trending/{period}",
     *     summary="Get trending ayahs",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="period",
     *         in="path",
     *         required=true,
     *         description="Period: day, week, or month",
     *         @OA\Schema(type="string", enum={"day", "week", "month"}, example="day")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of results",
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trending ayahs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TrendingResponse")
     *             )
     *         )
     *     )
     * )
     */
    public function getTrending(string $period, Request $request): JsonResponse
    {
        try {
            if (!in_array($period, ['day', 'week', 'month'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Period must be one of: day, week, month'
                ], 400);
            }

            $limit = $request->input('limit', 10);
            $days = match($period) {
                'day' => 1,
                'week' => 7,
                'month' => 30,
                default => 1,
            };

            $startDate = Carbon::now()->subDays($days);

            // Get most viewed ayahs from BotLog
            $trendingAyahs = BotLog::query()
                ->where('created_at', '>=', $startDate)
                ->where('webhook_endpoint_uri', 'webhook-quran-word')
                ->where('is_command', true)
                ->where('text', 'regexp', '/sure[0-9]+ayah[0-9]+')
                ->selectRaw('text, COUNT(*) as view_count')
                ->groupBy('text')
                ->orderByDesc('view_count')
                ->limit($limit)
                ->get()
                ->map(function($log) {
                    if (preg_match('/\/sure(\d+)ayah(\d+)/', $log->text, $matches)) {
                        return (object)[
                            'sura' => (int)$matches[1],
                            'aya' => (int)$matches[2],
                            'view_count' => $log->view_count,
                        ];
                    }
                    return null;
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'data' => TrendingAyahResource::collection($trendingAyahs),
                'period' => $period,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting trending ayahs', ['error' => $e->getMessage(), 'period' => $period]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get trending ayahs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/feed",
     *     summary="Get feed of ayahs with pagination",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Results per page",
     *         @OA\Schema(type="integer", example=20, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         required=false,
     *         description="Translation language",
     *         @OA\Schema(type="string", example="fa")
     *     ),
     *     @OA\Parameter(
     *         name="translator",
     *         in="query",
     *         required=false,
     *         description="Translator name",
     *         @OA\Schema(type="string", example="ansarian")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feed of ayahs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getFeed(Request $request): JsonResponse
    {
        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            $language = $request->input('language', 'fa');
            $translator = $request->input('translator');

            // Get user settings if available
            $userSettings = $this->getUser($request);
            if ($userSettings && !$language) {
                $language = $userSettings->setting('quran_translation_language') ?? 'fa';
            }
            if ($userSettings && !$translator) {
                $translator = $userSettings->setting('quran_translation_translator');
            }

            // Get ayahs with pagination (ordered by sura and ayah)
            $ayahs = QuranAyat::query()
                ->orderBy('sura')
                ->orderBy('aya')
                ->paginate($perPage, ['*'], 'page', $page);

            $feedData = $ayahs->map(function($ayah) use ($language, $translator, $userSettings) {
                // Get translation
                $translation = QuranHelper::getQuranTranslation($language, $translator, $ayah->sura, $ayah->aya, $userSettings);
                
                return [
                    'sura' => $ayah->sura,
                    'aya' => $ayah->aya,
                    'arabic_text' => $ayah->text,
                    'simple_text' => $ayah->simple,
                    'translation' => $translation->text ?? null,
                    'translation_language' => $translation->language ?? null,
                    'translation_translator' => $translation->translator_name ?? null,
                    'page' => $ayah->page,
                    'juz' => $ayah->juz,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'ayahs' => $feedData,
                    'current_page' => $ayahs->currentPage(),
                    'per_page' => $ayahs->perPage(),
                    'total' => $ayahs->total(),
                    'last_page' => $ayahs->lastPage(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting feed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quran/audio/{sura}/{ayah}",
     *     summary="Get audio file URL for an ayah",
     *     tags={"Quran"},
     *     @OA\Parameter(
     *         name="sura",
     *         in="path",
     *         required=true,
     *         description="Surah number (1-114)",
     *         @OA\Schema(type="integer", example=1, minimum=1, maximum=114)
     *     ),
     *     @OA\Parameter(
     *         name="ayah",
     *         in="path",
     *         required=true,
     *         description="Ayah number",
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="reciter",
     *         in="query",
     *         required=false,
     *         description="Reciter name (parhizgar or alafasy)",
     *         @OA\Schema(type="string", enum={"parhizgar", "alafasy"}, example="parhizgar")
     *     ),
     *     @OA\Parameter(
     *         name="chat_id",
     *         in="query",
     *         required=false,
     *         description="User chat_id for personalized reciter",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="User ID for mobile app",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audio URL",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="audio_url", type="string", format="uri"),
     *                 @OA\Property(property="reciter", type="string"),
     *                 @OA\Property(property="sura", type="integer"),
     *                 @OA\Property(property="ayah", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function getAudio(int $sura, int $ayah, Request $request): JsonResponse
    {
        try {
            // Get reciter
            $reciter = $request->input('reciter');
            $userSettings = $this->getUser($request);
            
            if (!$reciter && $userSettings) {
                $reciter = $userSettings->setting('mp3_reciter') ?? 'parhizgar';
            }
            if (!$reciter) {
                $reciter = 'parhizgar';
            }

            // Get ayah
            $ayahModel = QuranAyat::query()
                ->where('sura', $sura)
                ->where('aya', $ayah)
                ->first();

            if (!$ayahModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ayah not found'
                ], 404);
            }

            // Generate audio URL
            $audioUrl = QuranHelper::getAudioUrl($reciter, $ayahModel);

            return response()->json([
                'success' => true,
                'data' => [
                    'audio_url' => $audioUrl,
                    'reciter' => $reciter,
                    'sura' => $sura,
                    'ayah' => $ayah,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error getting audio URL', [
                'error' => $e->getMessage(),
                'sura' => $sura,
                'ayah' => $ayah
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get audio URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
