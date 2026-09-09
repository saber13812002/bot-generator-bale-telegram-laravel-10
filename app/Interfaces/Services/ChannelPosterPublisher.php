<?php

namespace App\Interfaces\Services;

interface ChannelPosterPublisher
{
    public function sendPrivateMessage(string $chatId, string $text, ?array $inlineKeyboardRows = null): void;

    public function sendTestMessage(string $channelChatId, string $text, string $platform = 'bale', ?string $botToken = null): bool;

    /**
     * @return array{success: bool, message_id: ?string}
     */
    public function publish(
        string $channelChatId,
        string $contentType,
        ?string $text,
        ?string $fileId,
        string $platform = 'bale',
        ?string $botToken = null
    ): array;

    public function answerCallback(?string $callbackId, string $text = ''): void;
}
