<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Interfaces\Services\ChannelPosterPublisher;
use App\Models\ChannelPosterDestination;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class TelegramChannelPosterPublisher implements ChannelPosterPublisher
{
    public function __construct(private Telegram $bot, private string $origin = 'bale')
    {
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
            Log::warning('[ChannelPoster] Test send failed', [
                'platform' => $platform,
                'channel_chat_id' => $channelChatId,
                'error' => $e->getMessage(),
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
        ?string $botToken = null
    ): bool {
        try {
            $caption = $text ?? '';

            if ($platform === ChannelPosterDestination::PLATFORM_EITAA) {
                $result = BotHelper::sendMessageEitaaSupport(
                    $caption !== '' ? $caption : trans('bot.channel_poster_empty_text'),
                    (string) $botToken,
                    $channelChatId,
                    'eitaa'
                );

                return $this->isOk($result);
            }

            $client = $this->clientFor($platform, $botToken);

            if ($platform === ChannelPosterDestination::PLATFORM_BALE && $fileId) {
                return $this->publishMedia($client, $channelChatId, $contentType, $caption, $fileId);
            }

            if ($platform === ChannelPosterDestination::PLATFORM_TELEGRAM && $fileId && in_array($contentType, ['photo', 'video', 'voice', 'audio'], true)) {
                if ($this->publishMedia($client, $channelChatId, $contentType, $caption, $fileId)) {
                    return true;
                }
            }

            $result = $client->sendMessage([
                'chat_id' => $channelChatId,
                'text' => $caption !== '' ? $caption : trans('bot.channel_poster_empty_text'),
            ]);

            return $this->isOk($result);
        } catch (Exception $e) {
            Log::error('[ChannelPoster] Publish failed', [
                'platform' => $platform,
                'channel_chat_id' => $channelChatId,
                'content_type' => $contentType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function publishMedia(Telegram $client, string $channelChatId, string $contentType, string $caption, string $fileId): bool
    {
        $result = null;
        if ($contentType === 'photo') {
            $result = $client->sendPhoto([
                'chat_id' => $channelChatId,
                'photo' => $fileId,
                'caption' => $caption,
            ]);
        } elseif ($contentType === 'video') {
            $result = $client->sendVideo([
                'chat_id' => $channelChatId,
                'video' => $fileId,
                'caption' => $caption,
            ]);
        } elseif ($contentType === 'voice') {
            $payload = [
                'chat_id' => $channelChatId,
                'voice' => $fileId,
            ];
            if ($caption !== '') {
                $payload['caption'] = $caption;
            }
            $result = $client->sendVoice($payload);
        } elseif ($contentType === 'audio') {
            $payload = [
                'chat_id' => $channelChatId,
                'audio' => $fileId,
            ];
            if ($caption !== '') {
                $payload['caption'] = $caption;
            }
            $result = $client->sendAudio($payload);
        } else {
            $result = $client->sendMessage([
                'chat_id' => $channelChatId,
                'text' => $caption !== '' ? $caption : trans('bot.channel_poster_empty_text'),
            ]);
        }

        return $this->isOk($result);
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
            ];
        }

        if (is_string($result)) {
            return mb_substr($result, 0, 200);
        }

        return $result;
    }
}
