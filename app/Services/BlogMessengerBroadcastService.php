<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Models\Messenger;
use Illuminate\Support\Facades\Log;
use Telegram;

class BlogMessengerBroadcastService
{
    /**
     * Broadcast text or media to all configured channels (Telegram, Bale, Eitaa).
     * Returns array keyed by platform with 'success' => bool, 'error' => string|null.
     */
    public function broadcast(
        Messenger $messenger,
        string $text,
        ?string $mediaType = null,
        ?string $fileId = null,
        string $caption = '',
        string $sourceType = 'telegram',
        string $sourceToken = ''
    ): array {
        $results = [];
        $content = $text ?: $caption;
        $fileUrl = null;

        if ($mediaType && $fileId && $sourceToken) {
            $fileUrl = $this->getFileUrl($sourceToken, $fileId, $sourceType);
        }

        if ($messenger->hasTelegram()) {
            $results['telegram'] = $this->sendToTelegram($messenger, $content, $mediaType, $fileId, $fileUrl, $caption);
        }

        if ($messenger->hasBale()) {
            $results['bale'] = $this->sendToBale($messenger, $content, $mediaType, $fileId, $fileUrl, $caption);
        }

        if ($messenger->hasEitaa()) {
            $results['eitaa'] = $this->sendToEitaa($messenger, $content, $mediaType, $fileUrl, $caption);
        }

        return $results;
    }

    private function sendToTelegram(
        Messenger $messenger,
        string $text,
        ?string $mediaType,
        ?string $fileId,
        ?string $fileUrl,
        string $caption
    ): array {
        try {
            $bot = new Telegram($messenger->telegram_bot_token, 'telegram');
            $chatId = $messenger->telegram_channel_chat_id;
            $content = $caption ?: $text;

            if ($mediaType && ($fileUrl || $fileId)) {
                $media = $fileUrl ?: $fileId;
                $this->sendMediaByType($bot, $chatId, $mediaType, $media, $content);
            } else {
                BotHelper::sendMessageByChatId($bot, $chatId, $text);
            }
            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Blog broadcast Telegram failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendToBale(
        Messenger $messenger,
        string $text,
        ?string $mediaType,
        ?string $fileId,
        ?string $fileUrl,
        string $caption
    ): array {
        try {
            $bot = new Telegram($messenger->bale_bot_token, 'bale');
            $chatId = $messenger->bale_channel_chat_id;
            $content = $caption ?: $text;

            if ($mediaType && ($fileUrl || $fileId)) {
                $media = $fileUrl ?: $fileId;
                $this->sendMediaByType($bot, $chatId, $mediaType, $media, $content);
            } else {
                BotHelper::sendMessageByChatId($bot, $chatId, $text);
            }
            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Blog broadcast Bale failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendToEitaa(Messenger $messenger, string $text, ?string $mediaType, ?string $fileUrl, string $caption): array
    {
        try {
            $content = $text ?: $caption;
            if ($mediaType && $fileUrl) {
                $eitaaBot = new Telegram($messenger->eitaa_bot_token, 'eitaa');
                BotHelper::sendAnyFileMessageEitaa(
                    $messenger->eitaa_channel_chat_id,
                    $fileUrl,
                    \Illuminate\Support\Str::limit($content, 50),
                    $eitaaBot,
                    $caption
                );
            } else {
                BotHelper::sendMessageEitaaSupport(
                    $content,
                    $messenger->eitaa_bot_token,
                    $messenger->eitaa_channel_chat_id,
                    'eitaa'
                );
            }
            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Blog broadcast Eitaa failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendMediaByType(Telegram $bot, $chatId, string $mediaType, string $mediaInput, string $caption): void
    {
        $content = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ];

        switch ($mediaType) {
            case 'photo':
                $content['photo'] = $mediaInput;
                $bot->sendPhoto($content);
                break;
            case 'video':
                $content['video'] = $mediaInput;
                $bot->sendVideo($content);
                break;
            case 'voice':
                $content['voice'] = $mediaInput;
                $bot->sendVoice($content);
                break;
            case 'audio':
                $content['audio'] = $mediaInput;
                $bot->sendAudio($content);
                break;
            case 'document':
                $content['document'] = $mediaInput;
                $bot->sendDocument($content);
                break;
            default:
                $content['document'] = $mediaInput;
                $bot->sendDocument($content);
        }
    }

    private function getFileUrl(string $token, string $fileId, string $type): ?string
    {
        try {
            if ($type === 'bale') {
                $url = "https://tapi.bale.ai/bot{$token}/getFile?file_id=" . urlencode($fileId);
            } else {
                $url = "https://api.telegram.org/bot{$token}/getFile?file_id=" . urlencode($fileId);
            }

            $response = @file_get_contents($url);
            if ($response === false) {
                return null;
            }
            $data = json_decode($response, true);
            if (!isset($data['result']['file_path'])) {
                return null;
            }
            $filePath = $data['result']['file_path'];
            if ($type === 'bale') {
                return "https://tapi.bale.ai/file/bot{$token}/{$filePath}";
            }
            return "https://api.telegram.org/file/bot{$token}/{$filePath}";
        } catch (\Throwable $e) {
            Log::warning('Blog broadcast getFileUrl failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
