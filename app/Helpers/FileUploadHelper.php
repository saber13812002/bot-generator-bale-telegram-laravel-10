<?php

namespace App\Helpers;

use App\Models\Bot;
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
                'file_type' => $fileType
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
     * دریافت اطلاعات bot از messenger
     *
     * @param Telegram $messenger
     * @return array [botId, botType]
     */
    private static function getBotInfo(Telegram $messenger): array
    {
        $botType = $messenger->BotType();
        $token = $messenger->Token();

        if (!$token) {
            return [null, null];
        }

        // دریافت bot_id از request (اولویت اول)
        $request = request();
        if ($request && $request->has('bot_id')) {
            $botId = $request->input('bot_id');
            if ($botId) {
                return [(int)$botId, $botType];
            }
        }

        // پیدا کردن bot_id از token
        $bot = null;
        if ($botType == 'telegram') {
            $bot = Bot::where('telegram_bot_token', $token)->first();
        } elseif ($botType == 'bale') {
            $bot = Bot::where('bale_bot_token', $token)->first();
        }

        if ($bot) {
            return [$bot->id, $botType];
        }

        return [null, null];
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
