<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
 *  - شکل دقیق پاکت پاسخ در مستندات تعریف نشده (OFFICIAL_DOC_NOT_FOUND)؛
 *    مشاهدهٔ زنده در پروتکشن (اکتبر ۲۰۲۶): {"status": <string>,
 *    "data": <array>}. تعیین موفقیت/شکست متمرکز در analyzeResponse()
 *    (پنج حالت) است؛ در لایه‌های دیگر parser موازی نسازید (M3).
 *  - ارسال فایل دو مرحله‌ای است:
 *      1) requestSendFile(type) → upload_url
 *      2) POST multipart با فیلد «file» به upload_url → file_id
 *      3) sendFile(chat_id, file_id, text) → message_id
 *  - CP-RUBIKA-MEDIA-UPLOAD-FIX: در مراحل ۱ و ۲ فیلدها (upload_url /
 *    file_id) در دو سطح خوانده می‌شوند — سطح اول (شکل مستند) و توی data
 *    (پاکت دو سطحی مشاهده‌شده در پروتکشن) — و پاسخ ردشدهٔ API با خطای
 *    واقعی خودش گزارش می‌شود (نه خطای عمومی «no upload_url»/«no file_id»).
 *  - نام متد webhook در صفحه methods «updateBotEndpoints» (جمع) و در صفحه
 *    مقدمه «updateBotEndpoint» (مفرد) آمده است؛ در اینجا نام صفحه methods
 *    استفاده شده و عدم‌تطابق مستند شده است.
 */
class RubikaBotClient
{
    public const BASE = 'https://botapi.rubika.ir/v3/';

    /**
     * پنج حالت پاسخ API روبیکا (CP-RUBIKA-RESPONSE-FIX / M2).
     *
     * HTTP 200 به‌تنهایی مدرک موفقیت نیست و نبودِ message_id به‌تنهایی
     * مدرک شکست نیست؛ حالت بر اساس مدرک واقعی در بدنهٔ پاسخ (یا HTTP
     * status در استثناها) تعیین می‌شود.
     */
    public const STATE_SUCCESS_WITH_MESSAGE_ID = 'success_with_message_id';
    public const STATE_SUCCESS_WITHOUT_MESSAGE_ID = 'success_without_message_id';
    public const STATE_API_REJECTED = 'api_rejected';
    public const STATE_TRANSPORT_FAILURE = 'transport_failure';
    public const STATE_UNKNOWN_RESULT = 'unknown_result';

    /**
     * کلمات موفقیت برای فیلد status — فرض مستندسازانهٔ محافظه‌کارانه.
     *
     * مقدار دقیق status در پاکت پاسخ مستند نیست (مشاهدهٔ زنده: رشته).
     * فقط این مقادیر «پذیرش صریح API» شمرده می‌شوند؛ هرچیز دیگر UNKNOWN
     * می‌ماند (ساختار غیرمستند فرض نمی‌شود).
     */
    public const SUCCESS_STATUS_WORDS = ['ok', 'success', 'true', 'done', 'completed', '200'];

    /**
     * کلمات شکست برای فیلد status (تطبیق زیررشته روی مقدار نرمال‌شده).
     */
    public const FAILURE_STATUS_WORDS = ['error', 'fail', 'denied', 'forbidden', 'unauthorized', 'exception', 'invalid'];

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
        $response = $this->postJson($method, $params, $timeout);

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException(
                'rubika: non-JSON response from ' . $method . ' (HTTP ' . $response->status() . ')'
            );
        }

        return $data;
    }

    /**
     * POST JSON به API (فقط لایهٔ حمل).
     *
     * خطای اتصال و HTTP>=400 را با پیام استاندارد wrap می‌کند؛ تصمیم
     * موفقیت/شکست با analyzeResponse() در فراخواننده است (parser
     * متمرکز — M3). جدا شدن از call() برای مراحل آپلود است که باید
     * پاسخ 2xx خام را تحلیل کنند (CP-RUBIKA-MEDIA-UPLOAD-FIX / M2).
     *
     * @throws RuntimeException خطای اتصال یا HTTP >= 400
     */
    private function postJson(string $method, array $params = [], int $timeout = 60): Response
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

        return $response;
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
     * مستندات رسمی خروجی sendMessage فقط فیلد «message_id» را تعریف می‌کند.
     * چون شکل دقیق پاکت پاسخ مستند نیست (OFFICIAL_DOC_NOT_FOUND)، هم‌معنای
     * غیرمستند «id» هم در سطح اول پذیرفته می‌شود تا خطای کاذب «admin نیست»
     * رخ ندهد — اما «chat_id» طبق قرارداد رسمی شناسهٔ چت است (در همهٔ
     * متدها فیلد جدا از message_id) و هرگز شناسهٔ پیام نیست؛ پس پذیرفته
     * نمی‌شود.
     *
     * CP-RUBIKA-RESPONSE-FIX (اکتبر ۲۰۲۶): پاکت مشاهده‌شده در پروتکشن
     * دو سطحی است {"status":..., "data":{...}} و message_id در توی data
     * قرار دارد؛ در سطح data فقط کلید مستند message_id اعتبار دارد
     * (هم‌معنای «id» در توی data فرض برقرار نمی‌شود).
     *
     * نکتهٔ امن (ممیزی CP-RELEASE-GATE): پاسخ‌های 2xx گاهی پاکت خطای
     * API هستند (code/error در سطح اول یا در توی data) — در آن حالت هیچ
     * id پذیرفته نمی‌شود تا 2xx ناقص هرگز «موفقیت قطعی» نشود.
     */
    public static function hasMessageId(array $response): bool
    {
        return self::extractMessageId($response) !== null;
    }

    /**
     * استخراج message_id از پاسخ (string) در صورت موجود بودن.
     *
     * اولویت: سطح اول message_id (مستند) → id (هم‌معنای غیرمستند) →
     * سطح data message_id (پاکت دو سطحی مشاهده‌شده در پروتکشن).
     *
     * «chat_id» عمداً رد شده است: در مستندات رسمی روبیکا (botapi.rubika.ir)
     * chat_id شناسهٔ چت/کانال مقصد است — ورودی sendMessage و در متدهای
     * edit/delete/forward همواره پارامتری جدا از message_id — بنابراین
     * به‌عنوان شناسهٔ پیام اعتبار ندارد و پذیرش آن باعث «موفقیت کاذب» و
     * ذخیرهٔ شناسهٔ نادرست در لاگ‌های انتشار می‌شد.
     *
     * اگر پاسخ کلید خطا (code / error / ...) در سطح اول یا در توی data
     * داشته باشد، در نظر گرفته می‌شود که HTTP 2xx با خطای سطح API پاسخ
     * داده و هیچ id — حتی message_id — از آن پذیرفته نمی‌شود.
     */
    public static function extractMessageId(array $response): ?string
    {
        if (self::hasErrorEnvelope($response)) {
            return null;
        }

        // سطح اول: message_id (مستند) سپس هم‌معنای غیرمستند id
        foreach (['message_id', 'id'] as $key) {
            if (self::isMessageIdValue($id = $response[$key] ?? null)) {
                return (string) $id;
            }
        }

        // سطح data (پاکت دو سطحی): فقط کلید مستند message_id —
        // هم‌معناهای غیرمستند در این سطح فرض برقرار نمی‌شود.
        if (is_array($response['data'] ?? null)) {
            if (self::isMessageIdValue($id = $response['data']['message_id'] ?? null)) {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * آیا مقدار داده‌شده یک message_id معتبر است (رشتهٔ غیرخالی یا int مثبت)؟
     */
    public static function isMessageIdValue(mixed $id): bool
    {
        return (is_string($id) && $id !== '') || (is_int($id) && $id > 0);
    }

    /**
     * آیا پاسخ شبیه پاکت خطای API است (حداقل یک کلید code / error)؟
     *
     * فقط نام کلیدها بررسی می‌شود — ارزشی دیده نمی‌شود (هم‌خوانی با
     * diagnose() که برای لاگ امن طراحی شده است).
     *
     * CP-RUBIKA-RESPONSE-FIX: کلیدها در دو سطح بررسی می‌شوند — سطح اول
     * و کلیدهای توی data (با پیشوند «data.») — چون پاکت واقعی دو سطحی
     * است و خطای API ممکن است داخل data بیاید.
     */
    private static function hasErrorEnvelope(array $response): bool
    {
        foreach (self::envelopeLevels($response) as $level) {
            foreach (array_keys($level) as $key) {
                $key = strtolower((string) $key);

                if (str_contains($key, 'error') || str_contains($key, 'code')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * سطوح قابل بررسی پاکت پاسخ: سطح اول + (در صورت آرایه بودن) data.
     *
     * عمق بیش از ۲ سطح عمداً بررسی نمی‌شود: فقط پاکت دو سطحی مشاهده‌شده
     * در پروتکشن معتبر است و ساختارهای عمیق‌تر غیرمستند فرض می‌شوند.
     */
    private static function envelopeLevels(array $response): array
    {
        $levels = [$response];

        if (is_array($response['data'] ?? null)) {
            $levels[] = $response['data'];
        }

        return $levels;
    }

    /**
     * مقدار فیلد status در سطح اول (مقدار رشته‌ای نرمال‌شده)، وگرنه null.
     *
     * CP-RUBIKA-RESPONSE-FIX: مقدار دقیق status در مستندات تعریف نشده؛
     * فقط رشته‌های واقعی قابل ارزیابی هستند (عدد/آرایه نادیده گرفته می‌شود).
     */
    public static function statusValue(array $response): ?string
    {
        $status = $response['status'] ?? null;

        if (! is_string($status)) {
            return null;
        }

        $status = mb_strtolower(trim($status));

        return $status === '' ? null : $status;
    }

    /**
     * آیا status پاسخ یکی از کلمات موفقیت مستندسازانه است؟
     *
     * فقط تطبیق دقیق (بعد از نرمال‌سازی) — زیررشته یا ساختار غیرمستند
     * «موفقیت» شمرده نمی‌شود.
     */
    public static function hasSuccessStatus(array $response): bool
    {
        $status = self::statusValue($response);

        return $status !== null && in_array($status, self::SUCCESS_STATUS_WORDS, true);
    }

    /**
     * آیا status پاسخ حاوی یکی از کلمات شکست است؟
     */
    public static function hasFailureStatus(array $response): bool
    {
        $status = self::statusValue($response);

        if ($status === null) {
            return false;
        }

        foreach (self::FAILURE_STATUS_WORDS as $word) {
            if (str_contains($status, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * آیا پاسخ حاوی شواهد واقعی عدم دسترسی (403 / forbidden / denied) است؟
     *
     * پیام «ربات ادمین نیست» در ویزارد فقط با این شواهد واقعی نمایش
     * داده می‌شود (CP-RUBIKA-RESPONSE-FIX / M4).
     */
    private static function hasPermissionDenial(array $response): bool
    {
        $status = self::statusValue($response);
        if ($status !== null
            && (str_contains($status, 'forbidden') || str_contains($status, 'denied'))) {
            return true;
        }

        foreach (self::envelopeLevels($response) as $level) {
            $code = $level['code'] ?? null;
            if ($code === 403 || $code === '403') {
                return true;
            }
        }

        return false;
    }

    /**
     * تحلیل متمرکز پاسخ API روبیکا (CP-RUBIKA-RESPONSE-FIX / M2+M3).
     *
     * تنها منبع تصمیم‌گیری موفقیت/شکست برای همهٔ مسیرها (پیام تست،
     * انتشار، انتشار صفی، لاگ نتیجه) — در لایه‌های بالاتر parser موازی
     * نسازید.
     *
     * ترتیب اولویت:
     *  1) پاکت خطا (code/error) → API_REJECTED (reason: error_envelope)
     *  2) status با کلمهٔ شکست → API_REJECTED (reason: failure_status)
     *  3) message_id معتبر → SUCCESS_WITH_MESSAGE_ID
     *  4) status با کلمهٔ موفقیت → SUCCESS_WITHOUT_MESSAGE_ID
     *  5) هرچیز دیگر → UNKNOWN_RESULT (reason: no_success_evidence)
     *     — ساختار غیرمستند (مثل result.id) عمداً موفقیت شمرده نمی‌شود.
     *
     * @return array{
     *     state: string,            یکی از STATE_*
     *     message_id: ?string,
     *     reason: ?string,
     *     http_status: ?int,        همیشه null — فراخواننده مقدار واقعی را می‌گذارد
     *     permission_denied: bool
     * }
     */
    public static function analyzeResponse(array $response): array
    {
        if (self::hasErrorEnvelope($response)) {
            return [
                'state' => self::STATE_API_REJECTED,
                'message_id' => null,
                'reason' => 'error_envelope',
                'http_status' => null,
                'permission_denied' => self::hasPermissionDenial($response),
            ];
        }

        if (self::hasFailureStatus($response)) {
            return [
                'state' => self::STATE_API_REJECTED,
                'message_id' => null,
                'reason' => 'failure_status',
                'http_status' => null,
                'permission_denied' => self::hasPermissionDenial($response),
            ];
        }

        if (($messageId = self::extractMessageId($response)) !== null) {
            return [
                'state' => self::STATE_SUCCESS_WITH_MESSAGE_ID,
                'message_id' => $messageId,
                'reason' => null,
                'http_status' => null,
                'permission_denied' => false,
            ];
        }

        if (self::hasSuccessStatus($response)) {
            return [
                'state' => self::STATE_SUCCESS_WITHOUT_MESSAGE_ID,
                'message_id' => null,
                'reason' => null,
                'http_status' => null,
                'permission_denied' => false,
            ];
        }

        return [
            'state' => self::STATE_UNKNOWN_RESULT,
            'message_id' => null,
            'reason' => 'no_success_evidence',
            'http_status' => null,
            'permission_denied' => false,
        ];
    }

    /**
     * طبقه‌بندی استثناهای حمل (transport) به پنج حالت پاسخ
     * (CP-RUBIKA-RESPONSE-FIX / M2).
     *
     *  - «HTTP 4xx/5xx» در پیام استثنا = پاسخ واقعی API بود →
     *    API_REJECTED (403/404 → permission_denied؛ 401 «نه» — توکن
     *    نامعتبر، نه عدم دسترسی).
     *  - time-out / خطای cURL / پاسخ غیر-JSON → TRANSPORT_FAILURE.
     *
     * @return array{
     *     state: string,
     *     message_id: ?string,
     *     reason: ?string,
     *     http_status: ?int,
     *     permission_denied: bool
     * }
     */
    public static function classifyTransportException(\Throwable $e): array
    {
        $message = $e->getMessage();

        if (preg_match('/HTTP ([45]\d{2})/', $message, $m) === 1) {
            $status = (int) $m[1];

            return [
                'state' => self::STATE_API_REJECTED,
                'message_id' => null,
                'reason' => 'http_' . $status,
                'http_status' => $status,
                'permission_denied' => in_array($status, [403, 404], true),
            ];
        }

        $reason = str_contains($message, 'non-JSON')
            ? 'non_json_response'
            : (str_contains($message, 'connection error') ? 'connection_error' : 'unknown_transport_error');

        return [
            'state' => self::STATE_TRANSPORT_FAILURE,
            'message_id' => null,
            'reason' => $reason,
            'http_status' => null,
            'permission_denied' => false,
        ];
    }

    /**
     * تشخیص ساختار پاسخ برای لاگ امن (بدون body خام — body ممکن است شامل
     * chat_id مقصد یا دادهٔ حساس باشد؛ فقط کلیدها و وضعیت‌ها ثبت می‌شوند).
     *
     * خروجی: [
     *   'shape' => [...],              کلیدهای سطح اول → نوع PHP
     *   'keys_count' => int,           فقط کلیدهای سطح اول
     *   'has_message_id' => bool,
     *   'has_success_status' => bool,  CP-RUBIKA-RESPONSE-FIX
     *   'has_failure_status' => bool,  CP-RUBIKA-RESPONSE-FIX
     *   'data_keys' => [...],          کلیدهای توی data (در صورت آرایه بودن)
     *   'error_keys' => [...],         کلیدهای خطا در هر دو سطح (پیشوند «data.»)
     * ]
     */
    public static function diagnose(array $response): array
    {
        $shape = [];
        $errorKeys = [];
        $dataKeys = [];

        foreach (array_keys($response) as $key) {
            $shape[(string) $key] = gettype($response[$key]);
            // «message_id» خودش فیلد موفقیت است و نباید به‌عنوان کلید خطا شمرده شود.
            if (str_contains(strtolower((string) $key), 'error')
                || str_contains(strtolower((string) $key), 'code')) {
                $errorKeys[] = (string) $key;
            }
        }

        if (is_array($response['data'] ?? null)) {
            foreach (array_keys($response['data']) as $key) {
                $dataKeys[] = (string) $key;
                if (str_contains(strtolower((string) $key), 'error')
                    || str_contains(strtolower((string) $key), 'code')) {
                    $errorKeys[] = 'data.' . $key;
                }
            }
        }

        return [
            'shape' => $shape,
            'keys_count' => count($shape),
            'has_message_id' => self::hasMessageId($response),
            'has_success_status' => self::hasSuccessStatus($response),
            'has_failure_status' => self::hasFailureStatus($response),
            'data_keys' => $dataKeys,
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
     * استخراج فیلد رشته‌ای از پاسخ — اولویت: سطح اول (شکل مستند API)
     * سپس توی data (پاکت دو سطحی مشاهده‌شده در پروتکشن — همان پاکتی
     * که message_id در توی data مشاهده شد).
     *
     * CP-RUBIKA-MEDIA-UPLOAD-FIX / M3: فقط نام‌های کلید مستند یا
     * مشاهده‌شده (upload_url / file_id) خوانده می‌شوند؛ ساختار جدیدی
     * فرض برقرار نمی‌شود.
     */
    public static function extractField(array $response, string $field): ?string
    {
        $value = $response[$field] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        if (is_array($response['data'] ?? null)) {
            $value = $response['data'][$field] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) && $value > 0) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * جزئیات امن ردشوندگی برای متن استثنا (M2): فقط مقدار متنی status
     * و نام کلیدهای خطا — بدون body خام، URL یا توکن.
     */
    private static function rejectionDetail(array $response): string
    {
        $detail = [];

        $status = self::statusValue($response);
        if ($status !== null) {
            $detail[] = 'status=' . $status;
        }

        $errorKeys = self::diagnose($response)['error_keys'];
        if ($errorKeys !== []) {
            $detail[] = 'error_keys=[' . implode(',', $errorKeys) . ']';
        }

        return $detail === [] ? '' : ' (' . implode(' ', $detail) . ')';
    }

    /**
     * لاگ امن ساختار پاسخ مراحل آپلود (CP-RUBIKA-MEDIA-UPLOAD-FIX / M2).
     *
     * فقط موارد غیرحساس ثبت می‌شوند: HTTP status، نوع دادهٔ JSON، کلیدها
     * (با نوع PHP)، مقدار متنی status، وجود فیلد هدف، کلیدهای خطا و
     * حالت پنج‌گانهٔ analyzeResponse — هرگز body خام، URL، توکن یا
     * محتوای فایل.
     */
    private static function logUploadDiagnostics(string $stage, int $httpStatus, mixed $json, string $field): void
    {
        try {
            $diagnostic = is_array($json) ? self::diagnose($json) : null;

            Log::info('[ChannelPoster][media] rubika-upload-diagnostics ' . $stage, [
                'http_status'    => $httpStatus,
                'json_type'      => is_array($json) ? 'object' : (is_null($json) ? 'null' : gettype($json)),
                'shape'          => $diagnostic['shape'] ?? null,
                'data_keys'      => $diagnostic['data_keys'] ?? [],
                'error_keys'     => $diagnostic['error_keys'] ?? [],
                'status'         => $diagnostic !== null ? self::statusValue($json) : null,
                'has_' . $field  => is_array($json) && self::extractField($json, $field) !== null,
                'state'          => $diagnostic !== null ? self::analyzeResponse($json)['state'] : 'non_json',
            ]);
        } catch (\Throwable $e) {
            // خطای لاگ نباید آپلود را مختل کند
        }
    }

    /**
     * مرحله ۱ ارسال فایل: دریافت آدرس آپلود.
     *
     * CP-RUBIKA-MEDIA-UPLOAD-FIX:
     *  - upload_url از سطح اول (مستند) و توی data (پاکت مشاهده‌شده در
     *    پروتکشن) خوانده می‌شود — ریشهٔ خطای «no upload_url» در انتشار
     *    واقعی: پاسخ موفق داخل data بسته شده بود.
     *  - اگر API درخواست را رد کرده باشد (error_envelope / status
     *    شکست)، خطای واقعی API حفظ و گزارش می‌شود — نه خطای عمومی
     *    «no upload_url» (M2).
     *  - ساختار پاسخ به‌صورت امن لاگ می‌شود (M2).
     *
     * @param string $fileType یکی از اعضای FileTypeEnum: File|Image|Voice|Video|Music|Gif
     */
    public function requestUploadUrl(string $fileType): string
    {
        $response = $this->postJson('requestSendFile', ['type' => $fileType]);
        $json = $response->json();

        if (! is_array($json)) {
            self::logUploadDiagnostics('stage1_request_upload_url', $response->status(), $json, 'upload_url');
            throw new RuntimeException(
                'rubika: non-JSON response from requestSendFile (HTTP ' . $response->status() . ')'
            );
        }

        $analysis = self::analyzeResponse($json);
        self::logUploadDiagnostics('stage1_request_upload_url', $response->status(), $json, 'upload_url');

        if ($analysis['state'] === self::STATE_API_REJECTED) {
            throw new RuntimeException(
                'rubika: API rejected requestSendFile (HTTP ' . $response->status() . ')'
                . ' — reason: ' . $analysis['reason']
                . self::rejectionDetail($json)
            );
        }

        $url = self::extractField($json, 'upload_url');
        if ($url === null) {
            throw new RuntimeException(
                'rubika: no upload_url in requestSendFile response'
                . ' (state: ' . $analysis['state'] . self::rejectionDetail($json) . ')'
            );
        }

        return $url;
    }

    /**
     * مرحله ۲: آپلود فایل به upload_url (multipart، فیلد «file»).
     *
     * CP-RUBIKA-MEDIA-UPLOAD-FIX:
     *  - file_id از سطح اول (مستند) و توی data (پاکت مشاهده‌شده در
     *    پروتکشن) خوانده می‌شود.
     *  - پاسخ ردشدهٔ API (HTTP 2xx با error_envelope / status شکست) با
     *    خطای واقعی گزارش می‌شود؛ پاسخ غیر-JSON با پیام جدا از
     *    «no file_id» گزارش می‌شود (M2/M4).
     *
     * @return string file_id برای استفاده در sendFile
     */
    public function uploadFile(string $uploadUrl, string $contents, string $fileName, string $mime): string
    {
        try {
            $response = Http::asMultipart()
                ->withHeaders(['Accept' => 'application/json'])
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

        $json = $response->json();
        if (! is_array($json)) {
            self::logUploadDiagnostics('stage2_binary_upload', $response->status(), $json, 'file_id');
            throw new RuntimeException(
                'rubika: non-JSON upload response (HTTP ' . $response->status() . ')'
            );
        }

        $analysis = self::analyzeResponse($json);
        self::logUploadDiagnostics('stage2_binary_upload', $response->status(), $json, 'file_id');

        if ($analysis['state'] === self::STATE_API_REJECTED) {
            throw new RuntimeException(
                'rubika: API rejected the file upload (HTTP ' . $response->status() . ')'
                . ' — reason: ' . $analysis['reason']
                . self::rejectionDetail($json)
            );
        }

        $fileId = self::extractField($json, 'file_id');
        if ($fileId === null) {
            throw new RuntimeException(
                'rubika: no file_id in upload response'
                . ' (state: ' . $analysis['state'] . self::rejectionDetail($json) . ')'
            );
        }

        return $fileId;
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
