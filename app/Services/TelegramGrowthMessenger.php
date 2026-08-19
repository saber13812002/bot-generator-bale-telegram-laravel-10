<?php

namespace App\Services;

use App\Interfaces\Services\GrowthMessenger;
use Illuminate\Support\Facades\Log;
use Telegram;
use Throwable;

class TelegramGrowthMessenger implements GrowthMessenger
{
    public function __construct(private Telegram $bot)
    {
    }

    public function send(
        string $chatId,
        string $text,
        ?array $inlineKeyboardRows = null,
        ?array $replyKeyboardRows = null,
        ?string $parseMode = null
    ): void {
        $content = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $content['parse_mode'] = $parseMode;
        }

        if ($inlineKeyboardRows) {
            $content['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboardRows,
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($replyKeyboardRows) {
            $content['reply_markup'] = json_encode([
                'keyboard' => $replyKeyboardRows,
                'resize_keyboard' => true,
                'is_persistent' => true,
            ], JSON_UNESCAPED_UNICODE);
        }

        $result = $this->bot->sendMessage($content);
        if (is_array($result) && (($result['ok'] ?? true) === false)) {
            Log::warning('[GrowthCompanion] Send failed', [
                'chat_id' => $chatId,
                'description' => $result['description'] ?? null,
            ]);
        }
    }

    public function answerCallback(?string $callbackId, string $text = ''): void
    {
        if (!$callbackId) {
            return;
        }

        $this->bot->answerCallbackQuery([
            'callback_query_id' => $callbackId,
            'text' => $text,
        ]);
    }

    public function editReplyMarkup(string $chatId, string|int $messageId, array $inlineKeyboardRows): bool
    {
        try {
            $result = $this->bot->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => json_encode([
                    'inline_keyboard' => $inlineKeyboardRows,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if (is_array($result) && (($result['ok'] ?? true) === false)) {
                Log::info('[GrowthCompanion] editReplyMarkup failed, will resend keyboard', [
                    'chat_id' => $chatId,
                    'description' => $result['description'] ?? null,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::info('[GrowthCompanion] editReplyMarkup exception, will resend keyboard', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
