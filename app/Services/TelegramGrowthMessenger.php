<?php

namespace App\Services;

use App\Interfaces\Services\GrowthMessenger;
use Illuminate\Support\Facades\Log;
use Telegram;

class TelegramGrowthMessenger implements GrowthMessenger
{
    public function __construct(private Telegram $bot)
    {
    }

    public function send(string $chatId, string $text, ?array $inlineKeyboardRows = null): void
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
}
