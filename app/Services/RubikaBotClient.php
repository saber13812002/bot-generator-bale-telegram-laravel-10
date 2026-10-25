<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * کلاینت ربات روبیکا (Rubika Bot API v3) — HTTP خام با Http facade.
 *
 * منبع: مستندات رسمی Bot API روبیکا (botapi.rubika.ir) — صفحات methods/models؛
 * جزئیات تحقیق در docs/features/platform-api-research.md.
 *
 * وضعیت: MOCK_TESTED (فقط با Http::fake). تا تست زنده با ربات واقعی انجام
 * شود، LIVE_TESTED محسوب نمی‌شود.
 *
 * نکات مهم از مستندات:
 *  - آدرس پایه: https://botapi.rubika.ir/v3/{token}/{method} — فقط POST JSON
 *  - توکن opaque است (فرمت رسمی اعلام نشده).
 *  - پاکت پاسخ در مستندات تعریف نشده (BLOCKED)؛ موفقیت = HTTP 2xx + وجود
 *    فیلد مورد انتظار (معمولاً message_id).
 *  - ارسال فایل دو مرحله‌ای است:
 *      1) requestSendFile(type) → upload_url
 *      2) POST multipart با فیلد «file» به upload_url → file_id
 *      3) sendFile(chat_id, file_id, text) → message_id
 *  - نام متد webhook در صفحه methods «updateBotEndpoints» (جمع) و در صفحه
 *    مقدمه «updateBotEndpoint» (مفرد) آمده است؛ در اینجا نام صفحه methods
 *    استفاده شده و عدم‌تطابق مستند شده است.
 */
class RubikaBotClient
{
    public const BASE = 'https://botapi.rubika.ir/v3/';

    /**
     * Update دریافت‌شده از webhook (برای سطح سازگار با SDK).
     * روبیکا POST JSON با این شکل می‌فرستد:
     * { "update": { "type": "...", "chat_id": "...", "new_message": { Message } } }
     */
    private array $updateData = [];

    /**
     * نگاشت contentType داخلی پروژه به FileTypeEnum روبیکا.
     * (Image: jpg/gif/png/webp 10MB، Video: mp4 50MB، Voice: mp3، Music: mp3،
     *  File: 50MB — Gif طبق مستندات در کلاس Image است.)
     */
    public static function fileTypeFor(string $contentType): string
    {
        return match ($contentType) {
            'photo' => 'Image',
            'video' => 'Video',
            'voice' => 'Voice',
            'audio' => 'Music',
            default => 'File',
        };
    }

    public function __construct(private readonly string $token)
    {
    }

    /**
     * فراخوانی عمومی یک متد API (POST JSON).
     *
     * @return array پاسخ JSON (شکل دقیق پاکت در مستندات تعریف نشده)
     *
     * @throws RuntimeException در صورت خطای HTTP (>=400) یا پاسخ غیر JSON
     */
    public function call(string $method, array $params = [], int $timeout = 60): array
    {
        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->timeout($timeout)
                ->post(self::BASE . $this->token . '/' . $method, $params);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'rubika: connection error calling ' . $method . ': ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($response->status() >= 400) {
            throw new RuntimeException(
                'rubika: HTTP ' . $response->status() . ' calling ' . $method
                . ' — ' . substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException(
                'rubika: non-JSON response from ' . $method . ' (HTTP ' . $response->status() . ')'
            );
        }

        return $data;
    }

    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /**
     * ---------------- سطح سازگار با SDK تلگرام ----------------
     * فقط برای مسیر webhook (LogHelper / BotHelper) استفاده می‌شود؛
     * فرم POST JSON یکسان با SDK است: ['chat_id'=>..., 'text'=>..., 'parse_mode'=>...]
     */

    public function setData(array $data): void
    {
        $this->updateData = $data;
    }

    public function getData(): array
    {
        return $this->updateData;
    }

    public function Text(): string
    {
        $u = $this->updateData['update'] ?? [];
        $t = $u['new_message']['text'] ?? '';

        return is_string($t) ? $t : '';
    }

    public function Caption(): string
    {
        $u = $this->updateData['update'] ?? [];
        $t = $u['new_message']['caption'] ?? '';

        return is_string($t) ? $t : '';
    }

    public function ChatID(): mixed
    {
        $u = $this->updateData['update'] ?? [];

        return $u['chat_id'] ?? $u['new_message']['chat_id'] ?? null;
    }

    /**
     * ارسال پیام متنی. پاسخ معمولاً شامل message_id است.
     */
    public function sendMessage(mixed $content, mixed $text = null, bool $disableNotification = false): array
    {
        if (is_array($content)) {
            // فراخوانی SDK-style: BotHelper::sendMessage
            // parse_mode در API روبیکا مستند نشده (BLOCKED) — حذف امن.
            $content = array_diff_key($content, ['parse_mode' => true]);

            return $this->call('sendMessage', $content);
        }

        // فراخوانی مستقیم (Phase 3): sendMessage($chatId, $text, $disableNotification)
        return $this->call('sendMessage', array_filter([
            'chat_id' => $content,
            'text' => $text !== null ? (string) $text : null,
            'disable_notification' => $disableNotification ?: null,
        ]));
    }

    /**
     * آیا پاسخ حاوی message_id غیرخالی است (معیار موفقیت روبیکا)؟
     *
     * مستندات رسمی خروجی sendMessage فقط فیلد «message_id» را تعریف می‌کند،
     * اما پاسخ‌های واقعی روبیکا (مشاهده‌شده در تولید) گاهی فیلد را با نام‌های
     * هم‌معنا برمی‌گردانند — چون شکل پاسخ در مستندات رسمی تعریف نشده
     * (OFFICIAL_DOC_NOT_FOUND برای پاکت پاسخ)، هم‌معناهای رایج تلگرام
     * (id / chat_id) هم پذیرفته می‌شوند تا خطای کاذب «admin نیست» رخ ندهد.
     */
    public static function hasMessageId(array $response): bool
    {
        return self::extractMessageId($response) !== null;
    }

    /**
     * استخراج message_id از پاسخ (string) در صورت موجود بودن.
     *
     * اولویت: message_id (مستند) → id → chat_id (هم‌معناهای غیرمستند).
     */
    public static function extractMessageId(array $response): ?string
    {
        foreach (['message_id', 'id', 'chat_id'] as $key) {
            $id = $response[$key] ?? null;

            if (is_string($id) && $id !== '') {
                return $id;
            }

            if (is_int($id) && $id > 0) {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * تشخیص ساختار پاسخ برای لاگ امن (بدون body خام — body ممکن است شامل
     * chat_id مقصد یا دادهٔ حساس باشد؛ فقط کلیدها و وضعیت‌ها ثبت می‌شوند).
     *
     * خروجی: ['shape' => [...], 'has_message_id' => bool, 'error_keys' => [...]]
     */
    public static function diagnose(array $response): array
    {
        $shape = [];
        $errorKeys = [];

        foreach (array_keys($response) as $key) {
            $shape[(string) $key] = gettype($response[$key]);
            // «message_id» خودش فیلد موفقیت است و نباید به‌عنوان کلید خطا شمرده شود.
            if (str_contains(strtolower((string) $key), 'error')
                || str_contains(strtolower((string) $key), 'code')) {
                $errorKeys[] = (string) $key;
            }
        }

        return [
            'shape' => $shape,
            'keys_count' => count($shape),
            'has_message_id' => self::hasMessageId($response),
            'error_keys' => $errorKeys,
        ];
    }

    /**
     * شناسهٔ یگانهٔ همبسته برای دنبال‌کردن رویدادها در لاگ (بدون دادهٔ حساس).
     */
    public static function correlationId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * مرحله ۱ ارسال فایل: دریافت آدرس آپلود.
     *
     * @param string $fileType یکی از اعضای FileTypeEnum: File|Image|Voice|Video|Music|Gif
     */
    public function requestUploadUrl(string $fileType): string
    {
        $data = $this->call('requestSendFile', ['type' => $fileType]);

        $url = $data['upload_url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('rubika: no upload_url in requestSendFile response');
        }

        return $url;
    }

    /**
     * مرحله ۲: آپلود فایل به upload_url (multipart، فیلد «file»).
     *
     * @return string file_id برای استفاده در sendFile
     */
    public function uploadFile(string $uploadUrl, string $contents, string $fileName, string $mime): string
    {
        try {
            $response = Http::asMultipart()
                ->timeout(300)
                ->attach('file', $contents, $fileName, ['Content-Type' => $mime])
                ->post($uploadUrl);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'rubika: connection error uploading file: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($response->status() >= 400) {
            throw new RuntimeException(
                'rubika: HTTP ' . $response->status() . ' uploading file'
                . ' — ' . substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();
        $fileId = is_array($data) ? ($data['file_id'] ?? null) : null;
        if (! is_string($fileId) && (! is_int($fileId))) {
            throw new RuntimeException('rubika: no file_id in upload response');
        }

        return (string) $fileId;
    }

    /**
     * مرحله ۳: ارسال فایل قبلاً آپلودشده به چت.
     *
     * پاسخ خام (Response) برمی‌گردد تا مصرف‌کننده (ChannelPosterMediaSender)
     * آن را از مسیر یکپارچه parseResponse بررسی کند.
     */
    public function sendFile(string $chatId, string $fileId, ?string $text = null): Response
    {
        $params = [
            'chat_id' => $chatId,
            'file_id' => $fileId,
        ];

        if ($text !== null && $text !== '') {
            $params['text'] = $text;
        }

        try {
            return Http::withHeaders(['Accept' => 'application/json'])
                ->timeout(60)
                ->post(self::BASE . $this->token . '/sendFile', $params);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'rubika: connection error calling sendFile: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * دریافت updates (getUpdates).
     * offset_id باید next_offset_idِ پاسخ قبلی باشد (رشته‌ای).
     */
    public function getUpdates(?string $offsetId = null, int $limit = 100): array
    {
        $params = ['limit' => $limit];
        if ($offsetId !== null && $offsetId !== '') {
            $params['offset_id'] = $offsetId;
        }

        return $this->call('getUpdates', $params);
    }

    /**
     * تعریف/به‌روزرسانی endpoint webhook (Phase 4).
     *
     * نکته: نام متد در صفحه methods «updateBotEndpoints» (جمع) و در صفحه
     * مقدمه مستندات «updateBotEndpoint» (مفرد) است. نام صفحه methods
     * (جمع) در اینجا استفاده می‌شود؛ عدم‌تطابق در platform-api-research.md
     * مستند شده است (BLOCKED تا تست زنده).
     *
     * @param string $url  آدرس HTTPS با دامنه عمومی
     * @param string $type یکی از UpdateEndpointTypeEnum: ReceiveUpdate|ReceiveInlineMessage|ReceiveQuery|GetSelectionItem|SearchSelectionItems
     */
    public function updateEndpoint(string $url, string $type): array
    {
        return $this->call('updateBotEndpoints', [
            'url' => $url,
            'type' => $type,
        ]);
    }
}
