<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * کلاینت ربات سoroush پلاس (Soroush Plus Bot API) — HTTP خام با Http facade.
 *
 * منبع: مستندات رسمی Bot API سoroush پلاس (https://api.splus.ir)؛
 * جزئیات تحقیق در docs/features/platform-api-research.md.
 *
 * وضعیت: MOCK_TESTED (فقط با Http::fake). تا تست زنده با ربات واقعی انجام
 * شود، LIVE_TESTED محسوب نمی‌شود.
 *
 * نکات مهم از مستندات:
 *  - آدرس پایه: https://api.splus.ir/bot<token>/<METHOD> (فقط HTTPS، POST/GET)
 *  - بدنه JSON یا فرم یا multipart هر سه پذیرفته می‌شود.
 *  - فرمت توکن دقیقاً هم‌سان تلگرام است: 123456:ABC-...
 *  - پاسخ: پاکت { ok, result, description, error_code }
 *  - sendPoll در فهرست رسمی متدها نیست (BLOCKED).
 *  - webhook: پورت‌های مجاز 443/80/88/8443.
 */
class SoroushBotClient
{
    public const BASE = 'https://api.splus.ir/bot';

    /**
     * Update دریافت‌شده از webhook (برای سطح سازگار با SDK).
     * سoroush پلاس Telegram-style update می‌فرستد:
     * message / edited_message / callback_query.
     */
    private array $updateData = [];

    public function __construct(private readonly string $token)
    {
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
        $m = $this->updateData['message'] ?? $this->updateData['edited_message'] ?? null;
        $t = $m['text'] ?? '';

        return is_string($t) ? $t : '';
    }

    public function Caption(): string
    {
        $m = $this->updateData['message'] ?? $this->updateData['edited_message'] ?? null;
        $t = $m['caption'] ?? '';

        return is_string($t) ? $t : '';
    }

    public function ChatID(): mixed
    {
        if (isset($this->updateData['callback_query'])) {
            return $this->updateData['callback_query']['message']['chat']['id']
                ?? $this->updateData['callback_query']['from']['id']
                ?? null;
        }

        $m = $this->updateData['message'] ?? $this->updateData['edited_message'] ?? null;

        return $m['chat']['id'] ?? null;
    }

    /**
     * فراخوانی عمومی یک متد API.
     *
     * @return array پاکت پاسخ { ok, result, description, error_code }
     *
     * @throws RuntimeException در صورت خطای اتصال یا پاسخ غیر JSON
     */
    public function call(string $method, array $params = [], int $timeout = 60): array
    {
        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->timeout($timeout)
                ->post(self::BASE . $this->token . '/' . $method, $params);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'soroush: connection error calling ' . $method . ': ' . $e->getMessage(),
                0,
                $e
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException(
                'soroush: non-JSON response from ' . $method . ' (HTTP ' . $response->status() . ')'
            );
        }

        return $data;
    }

    /** آیا پاکت پاسخ موفق است؟ */
    public static function isOk(array $envelope): bool
    {
        return ($envelope['ok'] ?? false) === true;
    }

    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /**
     * ارسال پیام متنی.
     *
     * @param string $chatId شناسه عددی چت یا @username
     * @param string $text    متن پیام (1 تا 4096 کاراکتر)
     */
    public function sendMessage(mixed $content): array
    {
        if (is_array($content)) {
            // فراخوانی SDK-style: BotHelper::sendMessage
            // parse_mode در API Soroush Plus مستند نشده (BLOCKED) — حذف امن.
            $content = array_diff_key($content, ['parse_mode' => true]);

            return $this->call('sendMessage', $content);
        }

        // فراخوانی مستقیم (Phase 3): sendMessage($chatId, $text)
        $text = func_num_args() >= 2 ? func_get_arg(1) : null;

        return $this->call('sendMessage', [
            'chat_id' => $content,
            'text' => (string) $text,
        ]);
    }

    /**
     * تعریف webhook (Phase 4 — در Phase 3 استفاده نمی‌شود).
     * پورت‌های مجاز طبق مستندات: 443/80/88/8443.
     */
    public function setWebhook(string $url, array $extra = []): array
    {
        return $this->call('setWebhook', array_merge([
            'url' => $url,
        ], $extra));
    }

    public function deleteWebhook(bool $dropPending = false): array
    {
        return $this->call('deleteWebhook', [
            'drop_pending_updates' => $dropPending,
        ]);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    /**
     * ارسال رسانه از طریق file_id یا آدرس HTTP.
     *
     * @param string $field نام فیلد رسانه: photo|video|voice|audio|document
     * @param string $fileIdOrUrl file_id درون‌بافتی یا URL مستقیم
     */
    public function sendMedia(string $field, string $chatId, string $fileIdOrUrl, ?string $caption = null): array
    {
        $method = match ($field) {
            'photo' => 'sendPhoto',
            'video' => 'sendVideo',
            'voice' => 'sendVoice',
            'audio' => 'sendAudio',
            default => 'sendDocument',
        };

        $params = [
            $field => $fileIdOrUrl,
            'chat_id' => $chatId,
        ];

        if ($caption !== null && $caption !== '') {
            $params['caption'] = $caption;
        }

        return $this->call($method, $params);
    }
}
