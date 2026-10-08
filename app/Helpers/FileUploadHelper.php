<?php

namespace App\Helpers;

use App\Models\Bot;
use App\Models\BotLog;
use App\Models\BotUploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram;

class FileUploadHelper
{
    /**
     * دریافت یا آپلود فایل
     * اگر فایل قبلاً آپلود شده باشد، file_id را برمی‌گرداند
     * در غیر این صورت، فایل را آپلود می‌کند و file_id را ذخیره می‌کند
     *
     * @param Telegram $messenger
     * @param string $fileUniqueKey
     * @param string $fileUrl
     * @param string $fileType
     * @param array $metadata
     * @param int|null $botId
     * @param string|null $botType
     * @return array|null
     */
    public static function getOrUploadFile(
        Telegram $messenger,
        string $fileUniqueKey,
        string $fileUrl,
        string $fileType,
        array $metadata = [],
        ?int $botId = null,
        ?string $botType = null
    ): ?array {
        // دریافت bot_id و bot_type
        if (!$botId || !$botType) {
            [$botId, $botType] = self::getBotInfo($messenger);
        }

        if (!$botId || !$botType) {
            Log::warning('⚠️ [FileUploadHelper] Bot ID or type not found', [
                'file_unique_key' => $fileUniqueKey,
                'file_type' => $fileType,
                'request_token' => substr((string) self::getRequestToken(), 0, 12) . '...',
                'messenger_token' => substr((string) $messenger->token(), 0, 12) . '...',
                'token_match' => self::getRequestToken() === $messenger->token(),
                'bot_type' => $botType,
            ]);
            return null;
        }

        // چک کردن آیا فایل قبلاً آپلود شده است
        $uploadedFile = BotUploadedFile::findByUniqueKey($botId, $botType, $fileUniqueKey);

        if ($uploadedFile) {
            Log::info('✅ [FileUploadHelper] File found in database', [
                'file_unique_key' => $fileUniqueKey,
                'file_id' => $uploadedFile->file_id,
                'bot_id' => $botId,
                'bot_type' => $botType
            ]);

            return [
                'file_id' => $uploadedFile->file_id,
                'file_unique_id' => $uploadedFile->file_unique_id,
                'uploaded_file' => $uploadedFile,
                'is_cached' => true
            ];
        }

        // فایل پیدا نشد، باید آپلود شود
        Log::info('📤 [FileUploadHelper] File not found, uploading...', [
            'file_unique_key' => $fileUniqueKey,
            'file_url' => $fileUrl,
            'bot_id' => $botId,
            'bot_type' => $botType
        ]);

        return self::uploadAndSaveFile($messenger, $fileUniqueKey, $fileUrl, $fileType, $metadata, $botId, $botType);
    }

    /**
     * آپلود فایل و ذخیره در دیتابیس
     *
     * @param Telegram $messenger
     * @param string $fileUniqueKey
     * @param string $fileUrl
     * @param string $fileType
     * @param array $metadata
     * @param int $botId
     * @param string $botType
     * @return array|null
     */
    private static function uploadAndSaveFile(
        Telegram $messenger,
        string $fileUniqueKey,
        string $fileUrl,
        string $fileType,
        array $metadata,
        int $botId,
        string $botType
    ): ?array {
        try {
            // دانلود فایل موقت
            $tempPath = self::downloadFile($fileUrl);

            if (!$tempPath) {
                Log::error('❌ [FileUploadHelper] Failed to download file', [
                    'file_url' => $fileUrl,
                    'file_unique_key' => $fileUniqueKey
                ]);
                return null;
            }

            // آپلود به تلگرام/بله
            $response = self::uploadFile($messenger, $tempPath, $fileType, $metadata);

            // پاک کردن فایل موقت
            @unlink($tempPath);

            if (!$response || !isset($response['ok']) || !$response['ok']) {
                Log::error('❌ [FileUploadHelper] Failed to upload file', [
                    'file_url' => $fileUrl,
                    'file_unique_key' => $fileUniqueKey,
                    'response' => $response
                ]);
                return null;
            }

            // استخراج file_id از response
            $fileId = self::extractFileId($response, $fileType);
            $fileUniqueId = self::extractFileUniqueId($response, $fileType);

            if (!$fileId) {
                Log::error('❌ [FileUploadHelper] File ID not found in response', [
                    'file_unique_key' => $fileUniqueKey,
                    'response' => $response
                ]);
                return null;
            }

            // ذخیره در دیتابیس
            $uploadedFile = self::saveUploadedFile(
                $botId,
                $botType,
                $fileUniqueKey,
                $fileType,
                $response,
                $metadata,
                $fileId,
                $fileUniqueId
            );

            Log::info('✅ [FileUploadHelper] File uploaded and saved', [
                'file_unique_key' => $fileUniqueKey,
                'file_id' => $fileId,
                'bot_id' => $botId,
                'bot_type' => $botType
            ]);

            return [
                'file_id' => $fileId,
                'file_unique_id' => $fileUniqueId,
                'uploaded_file' => $uploadedFile,
                'is_cached' => false
            ];
        } catch (\Exception $e) {
            Log::error('❌ [FileUploadHelper] Exception during upload', [
                'file_unique_key' => $fileUniqueKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * ذخیره اطلاعات فایل آپلود شده در دیتابیس
     *
     * @param int $botId
     * @param string $botType
     * @param string $fileUniqueKey
     * @param string $fileType
     * @param array $response
     * @param array $metadata
     * @param string $fileId
     * @param string|null $fileUniqueId
     * @return BotUploadedFile
     */
    public static function saveUploadedFile(
        int $botId,
        string $botType,
        string $fileUniqueKey,
        string $fileType,
        array $response,
        array $metadata = [],
        ?string $fileId = null,
        ?string $fileUniqueId = null
    ): BotUploadedFile {
        if (!$fileId) {
            $fileId = self::extractFileId($response, $fileType);
        }

        if (!$fileUniqueId) {
            $fileUniqueId = self::extractFileUniqueId($response, $fileType);
        }

        $uploadedFile = BotUploadedFile::updateOrCreate(
            [
                'bot_id' => $botId,
                'bot_type' => $botType,
                'file_unique_key' => $fileUniqueKey
            ],
            [
                'file_type' => $fileType,
                'file_id' => $fileId,
                'file_unique_id' => $fileUniqueId,
                'file_size' => self::extractFileSize($response, $fileType),
                'width' => self::extractWidth($response, $fileType),
                'height' => self::extractHeight($response, $fileType),
                'metadata' => $metadata,
                'upload_response' => $response
            ]
        );

        return $uploadedFile;
    }

    /**
     * دریافت file_id از دیتابیس
     *
     * @param int $botId
     * @param string $botType
     * @param string $fileUniqueKey
     * @return string|null
     */
    public static function getFileId(int $botId, string $botType, string $fileUniqueKey): ?string
    {
        return BotUploadedFile::getFileId($botId, $botType, $fileUniqueKey);
    }

    /**
     * ساخت کلید یونیک برای فایل
     *
     * @param string $fileType
     * @param array $params
     * @param string $botType
     * @return string
     */
    public static function generateFileUniqueKey(string $fileType, array $params, string $botType): string
    {
        $key = $fileType . '_';

        switch ($fileType) {
            case 'scan_page':
                $key .= $params['hr'] . '_' . $params['page'] . '_' . $botType;
                break;

            case 'audio_recitation':
                $key .= $params['reciter'] . '_' . $params['sura'] . '_' . $params['aya'] . '_' . $botType;
                break;

            case 'audio_translation':
                $key .= $params['locale'] . '_' . $params['sura'] . '_' . $params['aya'] . '_' . $botType;
                break;

            case 'audio_page':
                $key .= $params['page'] . '_' . $botType;
                break;

            default:
                // برای انواع دیگر فایل، از hash استفاده می‌کنیم
                $key .= md5(json_encode($params)) . '_' . $botType;
                break;
        }

        return $key;
    }

    /**
     * دریافت token ارسالی در request (query string یا body)
     *
     * @return string|null
     */
    private static function getRequestToken(): ?string
    {
        $request = request();
        if (!$request) {
            return null;
        }

        return $request->input('token') ?: $request->query('token');
    }

    /**
     * دریافت اطلاعات bot از messenger
     *
     * ترتیب تلاش برای پیدا کردن bot_id:
     * 1. bot_id مستقیم از request
     * 2. جستجو در جدول bots بر اساس token (messenger یا request)
     * 3. جستجو بر اساس bot_mother_id + endpoint_id + language + type (مشخصات webhook)
     * 4. جستجو بر اساس bot_mother_id + language + type
     * 5. جستجو در BotLog های اخیر بر اساس مشخصات webhook (مشابه LogHelper)
     *
     * @param Telegram $messenger
     * @return array [botId, botType]
     */
    private static function getBotInfo(Telegram $messenger): array
    {
        $botType = $messenger->BotType();
        $token = $messenger->token();

        if (!$botType) {
            return [null, null];
        }

        // 1. دریافت bot_id از request (اولویت اول)
        $request = request();
        if ($request && $request->has('bot_id')) {
            $botId = $request->input('bot_id');
            if ($botId) {
                return [(int)$botId, $botType];
            }
        }

        // 2. پیدا کردن bot_id از token (اول token خود messenger، سپس token request)
        $candidates = [];
        if ($token) {
            $candidates[] = $token;
        }
        $requestToken = self::getRequestToken();
        if ($requestToken && !in_array($requestToken, $candidates, true)) {
            $candidates[] = $requestToken;
        }

        foreach ($candidates as $candidateToken) {
            $bot = self::findBotByToken($candidateToken, $botType);
            if ($bot) {
                return [$bot->id, $botType];
            }
        }

        // 3 و 4. پیدا کردن bot از مشخصات webhook (bot_mother_id + endpoint + language + type)
        if ($request) {
            $bot = self::findBotFromWebhookContext($request, $botType);
            if ($bot) {
                return [$bot, $botType];
            }
        }

        return [null, null];
    }

    /**
     * پیدا کردن bot از token بر اساس نوع
     *
     * @param string $token
     * @param string $botType
     * @return Bot|null
     */
    private static function findBotByToken(string $token, string $botType): ?Bot
    {
        $query = Bot::query();

        if ($botType == 'telegram') {
            $query->where('telegram_bot_token', $token);
        } elseif ($botType == 'bale') {
            $query->where('bale_bot_token', $token);
        } else {
            return null;
        }

        return $query->first();
    }

    /**
     * پیدا کردن bot از مشخصات webhook (bot_mother_id، endpoint، language و type)
     *
     * @param \Illuminate\Http\Request $request
     * @param string $botType
     * @return int|null
     */
    private static function findBotFromWebhookContext(\Illuminate\Http\Request $request, string $botType): ?int
    {
        $botMotherId = $request->input('bot_mother_id') ?: $request->query('bot_mother_id');
        $language = $request->input('language') ?: $request->query('language');
        // segment(2) معمولاً نام endpoint است (مثلاً webhook-quran-word)
        $endpointUri = $request->segment(2);

        if (!$botMotherId || !$language || !$endpointUri) {
            return null;
        }

        // endpoint_id در جدول bots ممکن است با یا بدون پیشوند webhook- باشد
        $endpointId = $endpointUri;
        $endpointIdAlt = str_starts_with($endpointId, 'webhook-')
            ? substr($endpointId, strlen('webhook-'))
            : 'webhook-' . $endpointId;

        // 3. جستجو بر اساس bot_mother_id + endpoint_id + language + type
        $bot = Bot::where('bot_mother_id', $botMotherId)
            ->where('type', $botType)
            ->where('language_code', $language)
            ->whereIn('endpoint_id', [$endpointId, $endpointIdAlt])
            ->first();

        if ($bot) {
            return $bot->id;
        }

        // 4. جستجو بر اساس bot_mother_id + language + type (اگر فقط یک ربات دارد)
        $bots = Bot::where('bot_mother_id', $botMotherId)
            ->where('type', $botType)
            ->where('language_code', $language)
            ->get();

        if ($bots->count() === 1) {
            return $bots->first()->id;
        }

        // 5. جستجو در BotLog های اخیر (مشابه روش LogHelper)
        $botLog = BotLog::where('bot_mother_id', $botMotherId)
            ->where('type', $botType)
            ->where('webhook_endpoint_uri', $endpointUri)
            ->where('language', $language)
            ->whereNotNull('bot_id')
            ->where('bot_id', '!=', 1)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($botLog && Bot::whereKey($botLog->bot_id)->exists()) {
            return $botLog->bot_id;
        }

        return null;
    }

    /**
     * دانلود فایل از URL
     *
     * @param string $url
     * @return string|null
     */
    private static function downloadFile(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::error('❌ [FileUploadHelper] Failed to download file', [
                    'url' => $url,
                    'status' => $response->status()
                ]);
                return null;
            }

            $tempPath = storage_path('app/temp/' . uniqid('file_', true) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

            // اطمینان از وجود دایرکتوری
            $dir = dirname($tempPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($tempPath, $response->body());

            return $tempPath;
        } catch (\Exception $e) {
            Log::error('❌ [FileUploadHelper] Exception during download', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * آپلود فایل به تلگرام/بله
     *
     * @param Telegram $messenger
     * @param string $filePath
     * @param string $fileType
     * @param array $metadata
     * @return array|null
     */
    private static function uploadFile(Telegram $messenger, string $filePath, string $fileType, array $metadata): ?array
    {
        try {
            $chatId = $messenger->ChatID();
            $botType = $messenger->BotType();

            switch ($fileType) {
                case 'scan_page':
                case 'photo':
                    $content = [
                        'chat_id' => $chatId,
                        'photo' => new \CURLFile($filePath),
                        'caption' => $metadata['caption'] ?? ''
                    ];
                    if (!empty($metadata['reply_markup'])) {
                        $content['reply_markup'] = $metadata['reply_markup'];
                    }
                    return $messenger->sendPhoto($content);

                case 'audio_recitation':
                case 'audio_translation':
                case 'audio_page':
                case 'audio':
                    $content = [
                        'chat_id' => $chatId,
                        'audio' => new \CURLFile($filePath),
                        'title' => $metadata['title'] ?? '',
                        'caption' => $metadata['caption'] ?? ''
                    ];
                    if (!empty($metadata['reply_markup'])) {
                        $content['reply_markup'] = $metadata['reply_markup'];
                    }
                    return $messenger->sendAudio($content);

                case 'document':
                    $content = [
                        'chat_id' => $chatId,
                        'document' => new \CURLFile($filePath),
                        'caption' => $metadata['caption'] ?? ''
                    ];
                    return $messenger->sendDocument($content);

                case 'video':
                    $content = [
                        'chat_id' => $chatId,
                        'video' => new \CURLFile($filePath),
                        'caption' => $metadata['caption'] ?? ''
                    ];
                    return $messenger->sendVideo($content);

                default:
                    Log::warning('⚠️ [FileUploadHelper] Unknown file type', [
                        'file_type' => $fileType
                    ]);
                    return null;
            }
        } catch (\Exception $e) {
            Log::error('❌ [FileUploadHelper] Exception during upload', [
                'file_path' => $filePath,
                'file_type' => $fileType,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * استخراج file_id از response
     *
     * @param array $response
     * @param string $fileType
     * @return string|null
     */
    private static function extractFileId(array $response, string $fileType): ?string
    {
        if (!isset($response['result'])) {
            return null;
        }

        $result = $response['result'];

        switch ($fileType) {
            case 'scan_page':
            case 'photo':
                // برای عکس، file_id از بزرگترین سایز گرفته می‌شود
                if (isset($result['photo']) && is_array($result['photo']) && count($result['photo']) > 0) {
                    $lastPhoto = end($result['photo']);
                    return $lastPhoto['file_id'] ?? null;
                }
                break;

            case 'audio_recitation':
            case 'audio_translation':
            case 'audio_page':
            case 'audio':
                return $result['audio']['file_id'] ?? null;

            case 'document':
                return $result['document']['file_id'] ?? null;

            case 'video':
                return $result['video']['file_id'] ?? null;
        }

        return null;
    }

    /**
     * استخراج file_unique_id از response
     *
     * @param array $response
     * @param string $fileType
     * @return string|null
     */
    private static function extractFileUniqueId(array $response, string $fileType): ?string
    {
        if (!isset($response['result'])) {
            return null;
        }

        $result = $response['result'];

        switch ($fileType) {
            case 'scan_page':
            case 'photo':
                if (isset($result['photo']) && is_array($result['photo']) && count($result['photo']) > 0) {
                    $lastPhoto = end($result['photo']);
                    return $lastPhoto['file_unique_id'] ?? null;
                }
                break;

            case 'audio_recitation':
            case 'audio_translation':
            case 'audio_page':
            case 'audio':
                return $result['audio']['file_unique_id'] ?? null;

            case 'document':
                return $result['document']['file_unique_id'] ?? null;

            case 'video':
                return $result['video']['file_unique_id'] ?? null;
        }

        return null;
    }

    /**
     * استخراج file_size از response
     *
     * @param array $response
     * @param string $fileType
     * @return int|null
     */
    private static function extractFileSize(array $response, string $fileType): ?int
    {
        if (!isset($response['result'])) {
            return null;
        }

        $result = $response['result'];

        switch ($fileType) {
            case 'scan_page':
            case 'photo':
                if (isset($result['photo']) && is_array($result['photo']) && count($result['photo']) > 0) {
                    $lastPhoto = end($result['photo']);
                    return $lastPhoto['file_size'] ?? null;
                }
                break;

            case 'audio_recitation':
            case 'audio_translation':
            case 'audio_page':
            case 'audio':
                return $result['audio']['file_size'] ?? null;

            case 'document':
                return $result['document']['file_size'] ?? null;

            case 'video':
                return $result['video']['file_size'] ?? null;
        }

        return null;
    }

    /**
     * استخراج width از response
     *
     * @param array $response
     * @param string $fileType
     * @return int|null
     */
    private static function extractWidth(array $response, string $fileType): ?int
    {
        if (!isset($response['result'])) {
            return null;
        }

        $result = $response['result'];

        switch ($fileType) {
            case 'scan_page':
            case 'photo':
                if (isset($result['photo']) && is_array($result['photo']) && count($result['photo']) > 0) {
                    $lastPhoto = end($result['photo']);
                    return $lastPhoto['width'] ?? null;
                }
                break;

            case 'video':
                return $result['video']['width'] ?? null;
        }

        return null;
    }

    /**
     * استخراج height از response
     *
     * @param array $response
     * @param string $fileType
     * @return int|null
     */
    private static function extractHeight(array $response, string $fileType): ?int
    {
        if (!isset($response['result'])) {
            return null;
        }

        $result = $response['result'];

        switch ($fileType) {
            case 'scan_page':
            case 'photo':
                if (isset($result['photo']) && is_array($result['photo']) && count($result['photo']) > 0) {
                    $lastPhoto = end($result['photo']);
                    return $lastPhoto['height'] ?? null;
                }
                break;

            case 'video':
                return $result['video']['height'] ?? null;
        }

        return null;
    }
}
