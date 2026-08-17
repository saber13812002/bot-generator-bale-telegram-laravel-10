<?php

namespace App\Interfaces\Services;

interface GrowthMessenger
{
    public function send(string $chatId, string $text, ?array $inlineKeyboardRows = null): void;

    public function answerCallback(?string $callbackId, string $text = ''): void;
}
