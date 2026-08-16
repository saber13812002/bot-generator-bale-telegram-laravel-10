<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterPublisher;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class TelegramChannelPosterPublisher implements ChannelPosterPublisher
{
    public function __construct(private Telegram $bot)
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

    public function sendTestMessage(string $channelChatId, string $text): bool
    {
        try {
            $result = $this->bot->sendMessage([
                'chat_id' => $channelChatId,
                'text' => $text,
            ]);

            if (!$this->isOk($result)) {
                Log::warning('[ChannelPoster] Test send failed', [
                    'channel_chat_id' => $channelChatId,
                    'result' => $this->resultSummary($result),
                ]);

                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::warning('[ChannelPoster] Test send failed', [
                'channel_chat_id' => $channelChatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function publish(string $channelChatId, string $contentType, ?string $text, ?string $fileId): bool
    {
        try {
            $caption = $text ?? '';
            $result = null;

            if ($contentType === 'photo' && $fileId) {
                $result = $this->bot->sendPhoto([
                    'chat_id' => $channelChatId,
                    'photo' => $fileId,
                    'caption' => $caption,
                ]);
            } elseif ($contentType === 'video' && $fileId) {
                $result = $this->bot->sendVideo([
                    'chat_id' => $channelChatId,
                    'video' => $fileId,
                    'caption' => $caption,
                ]);
            } elseif ($contentType === 'voice' && $fileId) {
                $payload = [
                    'chat_id' => $channelChatId,
                    'voice' => $fileId,
                ];
                if ($caption !== '') {
                    $payload['caption'] = $caption;
                }
                $result = $this->bot->sendVoice($payload);
            } elseif ($contentType === 'audio' && $fileId) {
                $payload = [
                    'chat_id' => $channelChatId,
                    'audio' => $fileId,
                ];
                if ($caption !== '') {
                    $payload['caption'] = $caption;
                }
                $result = $this->bot->sendAudio($payload);
            } else {
                $result = $this->bot->sendMessage([
                    'chat_id' => $channelChatId,
                    'text' => $caption !== '' ? $caption : trans('bot.channel_poster_empty_text'),
                ]);
            }

            return $this->isOk($result);
        } catch (Exception $e) {
            Log::error('[ChannelPoster] Publish failed', [
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
