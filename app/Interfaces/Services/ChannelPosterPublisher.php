<?php

namespace App\Interfaces\Services;

interface ChannelPosterPublisher
{
    public function sendPrivateMessage(string $chatId, string $text, ?array $inlineKeyboardRows = null): void;

    public function sendTestMessage(string $channelChatId, string $text): bool;

    public function publish(string $channelChatId, string $contentType, ?string $text, ?string $fileId): bool;

    public function answerCallback(?string $callbackId, string $text = ''): void;
}
