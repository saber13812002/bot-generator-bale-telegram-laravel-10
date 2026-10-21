<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\TokenHelper;
use App\Interfaces\Services\ChannelPosterPublisher;
use App\Models\ChannelPosterDestination;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram;

class TelegramChannelPosterPublisher implements ChannelPosterPublisher
{
    public function __construct(
        private Telegram $bot,
        private string $origin = 'bale',
        private ?ChannelPosterMediaSender $sender = null
    ) {
    }

    public function sendPrivateMessage(string $chatId, string $text, ?array $inlineKeyboardRows = null): void
    {
        $content = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($inlineKeyboardRows) {
            $content['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboardRows,
            ], JSON_UNESCAPED_UNICODE);
        }

        $result = $this->bot->sendMessage($content);
        if (!$this->isOk($result)) {
            Log::warning('[ChannelPoster] Private send failed', [
                'chat_id' => $chatId,
                'result' => $this->resultSummary($result),
            ]);
        }
    }

    public function sendTestMessage(string $channelChatId, string $text, string $platform = 'bale', ?string $botToken = null): bool
    {
        try {
            if ($platform === ChannelPosterDestination::PLATFORM_EITAA) {
                $result = BotHelper::sendMessageEitaaSupport($text, (string) $botToken, $channelChatId, 'eitaa');

                return $this->isOk($result);
            }

            if ($platform === ChannelPosterDestination::PLATFORM_SOROUSH) {
                if (! is_string($botToken) || $botToken === '') {
                    Log::warning('[ChannelPoster] Test send failed', [
                        'platform' => 'soroush',
                        'error' => 'missing soroush bot token',
                    ]);

                    return false;
                }

                $result = (new SoroushBotClient($botToken))->sendMessage($channelChatId, $text);

                if (! SoroushBotClient::isOk($result)) {
                    Log::warning('[ChannelPoster] Test send failed', [
                        'platform' => 'soroush',
                        'channel_chat_id' => $channelChatId,
                        'result' => $this->resultSummary($result),
                    ]);

                    return false;
                }

                return true;
            }

            if ($platform === ChannelPosterDestination::PLATFORM_RUBIKA) {
                if (! is_string($botToken) || $botToken === '') {
                    Log::warning('[ChannelPoster] Test send failed', [
                        'platform' => 'rubika',
                        'error' => 'missing rubika bot token',
                    ]);

                    return false;
                }

                $client = new RubikaBotClient($botToken);
                $result = $client->sendMessage($channelChatId, $text);

                if (! $client->hasMessageId($result)) {
                    Log::warning('[ChannelPoster] Test send failed', [
                        'platform' => 'rubika',
                        'channel_chat_id' => $channelChatId,
                        'result' => $this->resultSummary($result),
                    ]);

                    return false;
                }

                return true;
            }

            $client = $this->clientFor($platform, $botToken);
            $result = $client->sendMessage([
                'chat_id' => $channelChatId,
                'text' => $text,
            ]);

            if (!$this->isOk($result)) {
                Log::warning('[ChannelPoster] Test send failed', [
                    'platform' => $platform,
                    'channel_chat_id' => $channelChatId,
                    'result' => $this->resultSummary($result),
                ]);

                return false;
            }

            return true;
        } catch (Exception $e) {
            // ماسک توکن: متن خطا ممکن است URL شامل توکن ربات باشد
            Log::warning('[ChannelPoster] Test send failed', [
                'platform' => $platform,
                'channel_chat_id' => $channelChatId,
                'error' => TokenHelper::redactTokens($e->getMessage()),
            ]);

            return false;
        }
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
        ?string $mimeType = null
    ): array {
        $caption = $text ?? '';
        $size = ($localPath !== null && is_file($localPath)) ? filesize($localPath) : 0;

        // لاگ امن (بدون توکن/URL): op، پلتفرم، نوع رسانه، حجم فایل
        Log::info('[ChannelPoster][publish] publish-start', [
            'op'         => substr(bin2hex(random_bytes(4)), 0, 8),
            'platform'   => $platform,
            'media'      => $contentType,
            'bytes'      => $size,
            'caption'    => $caption !== '',
            'has_file_id'=> $fileId !== null && $fileId !== '',
        ]);

        try {
            $isMedia = in_array($contentType, ['photo', 'video', 'voice', 'audio', 'document'], true);

            // ── 1) فایل روی دیسک → آپلود multipart (هر سه پلتفرم) ─────────────
            // file_idِ مبدأ برای پلتفرم‌های دیگر اعتبار ندارد؛ این راه‌حل اصلی
            // انتشار چندکاناله است. اگر محتوا رسانه باشد و آپلود ناموفق شود،
            // به‌هیچ‌وجه fallback به متن نمی‌شود (جلوگیری از موفقیت کاذب).
            if ($isMedia
                && $localPath !== null && $localPath !== '' && is_file($localPath)
                && $this->sender !== null
            ) {
                $mediaResult = $this->sender->send(
                    (string) $botToken,
                    $platform,
                    $channelChatId,
                    $contentType,
                    $localPath,
                    $caption !== '' ? $caption : null,
                    $fileName,
                    $mimeType
                );

                if ($mediaResult['success']) {
                    Log::info('[ChannelPoster][publish] publish-ok (media upload)', [
                        'platform' => $platform,
                        'media'    => $contentType,
                    ]);

                    return $mediaResult;
                }

                // file_id برای پلتفرم مبدأ هم ممکن است (مثلاً بله → بله).
                $fileIdResult = $this->tryFileId($fileId, $platform, $channelChatId, $contentType, $caption, $botToken);
                if ($fileIdResult !== null) {
                    return $fileIdResult;
                }

                Log::warning('[ChannelPoster][publish] publish-failed (media upload, no fallback)', [
                    'platform' => $platform,
                    'media'    => $contentType,
                    'error'    => $mediaResult['error'],
                ]);

                return $this->withError(
                    ['success' => false, 'message_id' => null],
                    $mediaResult['error'] ?? 'media upload failed'
                );
            }

            // ── 2) بدون فایل محلی: file_id فقط روی پلتفرم مبدأ معتبر است ────────
            if ($fileId !== null && $fileId !== '') {
                $fileIdResult = $this->tryFileId($fileId, $platform, $channelChatId, $contentType, $caption, $botToken);
                if ($fileIdResult !== null) {
                    return $fileIdResult;
                }
            }

            // ── 2b) رسانه بدون فایل قابل‌استفاده برای پلتفرم مقصد ────────────────
            // اگر رسانه باشد ولی فایل محلی موجود نباشد (دانلود ناموفق/موفق نبود)
            // و file_id هم فقط روی مبدأ معتبر باشد، انتشار فقط‌متن یعنی «موفقیت
            // کاذب». باید به‌طور صریح ناموفق گزارش شود تا در گزارش/ریتری ظاهر شود.
            if ($isMedia
                && in_array($platform, [
                    ChannelPosterDestination::PLATFORM_BALE,
                    ChannelPosterDestination::PLATFORM_TELEGRAM,
                    ChannelPosterDestination::PLATFORM_EITAA,
                    ChannelPosterDestination::PLATFORM_SOROUSH,
                    ChannelPosterDestination::PLATFORM_RUBIKA,
                ], true)
                && $platform !== $this->origin
            ) {
                Log::warning('[ChannelPoster][publish] publish-failed (media without usable file)', [
                    'platform' => $platform,
                    'media'    => $contentType,
                ]);

                return ['success' => false, 'message_id' => null, 'error' => 'media file unavailable for this platform'];
            }

            // ── 3) متن خالص ──────────────────────────────────────────────
            if ($platform === ChannelPosterDestination::PLATFORM_EITAA) {
                $result = BotHelper::sendMessageEitaaSupport(
                    $caption !== '' ? $caption : trans('bot.channel_poster_empty_text'),
                    (string) $botToken,
                    $channelChatId,
                    'eitaa'
                );

                return $this->withError(
                    ['success' => $this->isOk($result), 'message_id' => $this->extractMessageId($result)],
                    $result
                );
            }

            if ($platform === ChannelPosterDestination::PLATFORM_SOROUSH) {
                // سoroush پلاس: آدرس پایه و پاکت پاسخ هم‌فرمت تلگرام —
                // کلاینت مستقل SoroushBotClient (HTTP خام با Http facade).
                if (! is_string($botToken) || $botToken === '') {
                    return ['success' => false, 'message_id' => null, 'error' => 'missing soroush bot token'];
                }

                $result = (new SoroushBotClient($botToken))->sendMessage(
                    $channelChatId,
                    $caption !== '' ? $caption : trans('bot.channel_poster_empty_text')
                );

                $ok = SoroushBotClient::isOk($result);
                if (! $ok) {
                    Log::warning('[ChannelPoster][publish] publish-failed (soroush text)', [
                        'platform' => 'soroush',
                        'result' => $this->resultSummary($result),
                    ]);
                }

                return $this->withError(
                    ['success' => $ok, 'message_id' => $this->extractMessageId($result)],
                    $result
                );
            }

            if ($platform === ChannelPosterDestination::PLATFORM_RUBIKA) {
                // روبیکا: پاکت پاسخ مستند نشده — موفقیت یعنی وجود message_id.
                if (! is_string($botToken) || $botToken === '') {
                    return ['success' => false, 'message_id' => null, 'error' => 'missing rubika bot token'];
                }

                $client = new RubikaBotClient($botToken);
                $result = $client->sendMessage(
                    $channelChatId,
                    $caption !== '' ? $caption : trans('bot.channel_poster_empty_text')
                );

                $ok = $client->hasMessageId($result);
                if (! $ok) {
                    Log::warning('[ChannelPoster][publish] publish-failed (rubika text)', [
                        'platform' => 'rubika',
                        'result' => $this->resultSummary($result),
                    ]);
                }

                return [
                    'success' => $ok,
                    'message_id' => $client->extractMessageId($result),
                ] + ($ok ? [] : ['error' => 'rubika: no message_id in sendMessage response']);
            }

            $client = $this->clientFor($platform, $botToken);
            $result = $client->sendMessage([
                'chat_id' => $channelChatId,
                'text' => $caption !== '' ? $caption : trans('bot.channel_poster_empty_text'),
            ]);

            return $this->withError(
                ['success' => $this->isOk($result), 'message_id' => $this->extractMessageId($result)],
                $result
            );
        } catch (Exception $e) {
            // ماسک توکن: متن خطا ممکن است URL شامل توکن ربات باشد
            $safeError = TokenHelper::redactTokens($e->getMessage());
            Log::error('[ChannelPoster][publish] publish-failed (exception)', [
                'platform'   => $platform,
                'media'      => $contentType,
                'error'      => $safeError,
            ]);

            return ['success' => false, 'message_id' => null, 'error' => $safeError];
        }
    }

    /**
     * انتشار با file_id — فقط وقتی file_id برای همان پلتفرم معتبر است
     * (file_id از پلتفرم مبدأ آمده؛ فقط روی پلتفرم مبدأ قابل استفاده است).
     * در غیر این صورت null برمی‌گرداند.
     *
     * با Laravel Http Client (form) انجام می‌شود تا: TLS verify روشن بماند
     * (کلینت vendor این گزینه را خاموش می‌کند) و مسیر در تست‌ها قابل فیک باشد.
     */
    private function tryFileId(?string $fileId, string $platform, string $channelChatId, string $contentType, string $caption, ?string $botToken): ?array
    {
        if ($fileId === null || $fileId === '') {
            return null;
        }

        if (!in_array($contentType, ['photo', 'video', 'voice', 'audio', 'document'], true)) {
            return null;
        }

        // روبیکا: ارسال رسانه با file_id به‌صورت مستقیم (typed) وجود ندارد —
        // تنها مسیر روبیکا آپلود سه‌مرحله‌ای از فایل محلی است (ChannelPosterMediaSender).
        if ($platform === ChannelPosterDestination::PLATFORM_RUBIKA) {
            return null;
        }

        // file_id فقط روی پلتفرم مبدأ (محل دانلود فایل) معتبر است
        if ($platform !== $this->origin) {
            return null;
        }

        // اگر مقصد توکن خودش را نداشته، از توکن بوت مبدأ استفاده می‌شود
        $token = ($botToken !== null && $botToken !== '') ? $botToken : $this->bot->token();
        if (!is_string($token) || $token === '') {
            return null;
        }

        $base = match ($this->origin) {
            ChannelPosterDestination::PLATFORM_BALE => 'https://tapi.bale.ai/bot' . $token,
            ChannelPosterDestination::PLATFORM_SOROUSH => 'https://api.splus.ir/bot' . $token,
            default => 'https://api.telegram.org/bot' . $token,
        };

        $endpoint = match ($contentType) {
            'photo'    => 'sendPhoto',
            'video'    => 'sendVideo',
            'voice'    => 'sendVoice',
            'audio'    => 'sendAudio',
            'document' => 'sendDocument',
            default    => 'sendPhoto',
        };

        $form = [
            'chat_id' => $channelChatId,
            $contentType => $fileId,
        ];
        if ($caption !== '') {
            $form['caption'] = $caption;
        }

        $response = Http::asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(120)
            ->post($base . '/' . $endpoint, $form);

        $data = $response->json();
        if (is_array($data) && ($data['ok'] ?? false) === true) {
            $messageId = $data['result']['message_id'] ?? null;

            return [
                'success'    => true,
                'message_id' => $messageId !== null ? (string) $messageId : null,
                'error'      => null,
            ];
        }

        $desc = is_array($data) ? ($data['description'] ?? $data['error'] ?? null) : null;

        return [
            'success'    => false,
            'message_id' => null,
            'error'      => 'file_id publish failed'
                . (is_scalar($desc) && trim((string) $desc) !== '' ? ': ' . mb_substr((string) $desc, 0, 200) : ''),
        ];
    }

    /**
     * افزودن error به خروجی publish (برای گزارش‌رسانی دقیق به مالک/لاگ)
     */
    private function withError(array $result, mixed $raw): array
    {
        if (!$result['success']) {
            $result['error'] = $this->resultSummary($raw);
            if (is_array($result['error'])) {
                $result['error'] = json_encode($result['error'], JSON_UNESCAPED_UNICODE);
            }
        }

        return $result;
    }

    public function answerCallback(?string $callbackId, string $text = ''): void
    {
        if (!$callbackId) {
            return;
        }

        try {
            $payload = ['callback_query_id' => $callbackId];
            if ($text !== '') {
                $payload['text'] = $text;
            }
            $this->bot->answerCallbackQuery($payload);
        } catch (Exception $e) {
            Log::warning('[ChannelPoster] answerCallback failed', [
                'error' => TokenHelper::redactTokens($e->getMessage()),
            ]);
        }
    }

    private function clientFor(string $platform, ?string $botToken): Telegram
    {
        if ($platform === ChannelPosterDestination::PLATFORM_TELEGRAM && $botToken) {
            return new Telegram($botToken);
        }

        if ($platform === ChannelPosterDestination::PLATFORM_BALE && $botToken) {
            return new Telegram($botToken, 'bale');
        }

        return $this->bot;
    }

    private function isOk(mixed $result): bool
    {
        $result = $this->normalizeResult($result);

        if (is_array($result) && array_key_exists('ok', $result)) {
            return (bool) $result['ok'];
        }

        return (bool) $result;
    }

    private function extractMessageId(mixed $result): ?string
    {
        $normalized = $this->normalizeResult($result);
        if (!is_array($normalized)) {
            return null;
        }

        $messageId = $normalized['result']['message_id']
            ?? $normalized['message_id']
            ?? null;

        return $messageId !== null ? (string) $messageId : null;
    }

    private function normalizeResult(mixed $result): mixed
    {
        if (is_string($result) && $result !== '') {
            $decoded = json_decode($result, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_object($result)) {
            $encoded = json_decode(json_encode($result), true);
            if (is_array($encoded)) {
                return $encoded;
            }
        }

        return $result;
    }

    private function resultSummary(mixed $result): mixed
    {
        $normalized = $this->normalizeResult($result);
        if (is_array($normalized)) {
            return [
                'ok' => $normalized['ok'] ?? null,
                'description' => $normalized['description'] ?? null,
                'error_code' => $normalized['error_code'] ?? null,
                'message_id' => $normalized['message_id'] ?? $normalized['result']['message_id'] ?? null,
            ];
        }

        if (is_string($result)) {
            return mb_substr($result, 0, 200);
        }

        return $result;
    }
}
