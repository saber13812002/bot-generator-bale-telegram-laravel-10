<?php

namespace App\Interfaces\Services;

interface GrowthMessenger
{
    public function send(
        string $chatId,
        string $text,
        ?array $inlineKeyboardRows = null,
        ?array $replyKeyboardRows = null,
        ?string $parseMode = null
    ): void;

    public function answerCallback(?string $callbackId, string $text = ''): void;

    public function editReplyMarkup(string $chatId, string|int $messageId, array $inlineKeyboardRows): bool;
}
