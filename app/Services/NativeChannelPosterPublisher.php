<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterPublisher;
use Illuminate\Support\Facades\Log;
use Telegram;

/**
 * Publisher مبدأ (origin) برای پلتفرم‌هایی که SDK vendor ندارند:
 * Soroush Plus و Rubika (Phase 5 — engine generalization).
 *
 * طراحی:
 *  - sendPrivateMessage → مستقیم با کلاینت داخلی (SoroushBotClient /
 *    RubikaBotClient). کیبورد inline در هر دو پلتفرم مستند نیست
 *    (BLOCKED) — ردیف‌ها حذف و در لاگ ثبت می‌شوند تا هیچ‌گاه silent
 *    fail نباشد.
 *  - publish / sendTestMessage → تفویض به TelegramChannelPosterPublisher
 *    (موتور مقصد 5-پلتفرمی Phase 3). این موتور برای مقصدهای
 *    soroush/rubika از همین کلاینت‌های داخلی استفاده می‌کند و برای
 *    مقصدهای دیگر (bale/telegram/eitaa) از SDK/Http.
 *    تفویض lazy است: instance vendor فقط در زمان publish ساخته می‌شود،
 *    نه در webhook /start.
 *  - answerCallback → no-op + لاگ: answerCallbackQuery در Soroush Plus
 *    تأییدنشده است و روبیکا اصلاً callback ندارد (BLOCKED).
 *
 * وضعیت: MOCK_TESTED (فقط با Http::fake). LIVE_TESTED در Phase 7.
 */
class NativeChannelPosterPublisher implements ChannelPosterPublisher
{
    private ?ChannelPosterPublisher $delegate = null;

    public function __construct(
        private readonly string $token,
        private readonly string $origin,
        private readonly ?ChannelPosterMediaSender $sender = null
    ) {
    }

    public function sendPrivateMessage(string $chatId, string $text, ?array $inlineKeyboardRows = null): void
    {
        if ($inlineKeyboardRows) {
            // کیبورد inline در Soroush Plus / Rubika مستند نیست (BLOCKED).
            // متن ارسال می‌شود؛ ردیف‌ها در لاگ ثبت می‌شوند (هرگز silent fail).
            Log::info('[ChannelPoster] Inline keyboard dropped (not documented for origin)', [
                'origin' => $this->origin,
                'chat_id' => $chatId,
                'rows' => count($inlineKeyboardRows),
            ]);
        }

        try {
            if ($this->origin === 'rubika') {
                $client = new RubikaBotClient($this->token);
                $result = $client->sendMessage($chatId, $text);
                if (! $client->hasMessageId($result)) {
                    Log::warning('[ChannelPoster] Native private send failed', [
                        'origin' => 'rubika',
                        'chat_id' => $chatId,
                        'error' => 'no message_id in response',
                    ]);
                }

                return;
            }

            // default: soroush (و هر origin native دیگر با پاکت ok)
            $result = (new SoroushBotClient($this->token))->sendMessage($chatId, $text);
            if (! SoroushBotClient::isOk($result)) {
                Log::warning('[ChannelPoster] Native private send failed', [
                    'origin' => $this->origin,
                    'chat_id' => $chatId,
                    'error' => 'ok=false in response envelope',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ChannelPoster] Native private send exception', [
                'origin' => $this->origin,
                'chat_id' => $chatId,
                'error' => \App\Helpers\TokenHelper::redactTokens($e->getMessage()),
            ]);
        }
    }

    public function sendTestMessage(string $channelChatId, string $text, string $platform = 'bale', ?string $botToken = null): bool
    {
        return $this->delegate()->sendTestMessage($channelChatId, $text, $platform, $botToken);
    }

    public function publish(
        string $channelChatId,
        string $contentType,
        ?string $text,
        ?string $fileId,
        string $platform = 'bale',
        ?string $botToken = null,
        ?string $localPath = null,
        ?string $fileName = null,
        ?string $mimeType = null,
        ?string $parseMode = null,
        ?array $replyMarkup = null
    ): array {
        return $this->delegate()->publish(
            $channelChatId,
            $contentType,
            $text,
            $fileId,
            $platform,
            $botToken,
            $localPath,
            $fileName,
            $mimeType,
            $parseMode,
            $replyMarkup
        );
    }

    public function answerCallback(?string $callbackId, string $text = ''): void
    {
        if (!$callbackId) {
            return;
        }

        // answerCallbackQuery در Soroush Plus تأییدنشده است و Rubika
        // callback ندارد (BLOCKED) — no-op آگاهانه با لاگ.
        Log::info('[ChannelPoster] answerCallback skipped (undocumented for origin)', [
            'origin' => $this->origin,
            'callback_id' => $callbackId,
        ]);
    }

    /**
     * موتور مقصد 5-پلتفرمی (Phase 3). lazy ساخته می‌شود تا مسیرهای
     * webhook که فقط sendPrivateMessage می‌زنند هرگز instance vendor
     * نسازند (ساخت vendor با توکن opaque soroush/rubika بی‌خطر است —
     * همان کار factory قدیمی می‌کرد — اما لازم نیست).
     */
    private function delegate(): ChannelPosterPublisher
    {
        if ($this->delegate === null) {
            $this->delegate = new TelegramChannelPosterPublisher(
                new Telegram($this->token),
                $this->origin,
                $this->sender
            );
        }

        return $this->delegate;
    }
}
