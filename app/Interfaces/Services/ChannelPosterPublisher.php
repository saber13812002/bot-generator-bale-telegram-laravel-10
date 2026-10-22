<?php

namespace App\Interfaces\Services;

interface ChannelPosterPublisher
{
    public function sendPrivateMessage(string $chatId, string $text, ?array $inlineKeyboardRows = null): void;

    public function sendTestMessage(string $channelChatId, string $text, string $platform = 'bale', ?string $botToken = null): bool;

    /**
     * @param string|null $localPath مسیر مطلق فایل رسانه‌ای روی دیسک (برای آپلود multipart؛
     *                              به‌جای file_id که فقط برای پلتفرم مبدأ معتبر است)
     * @return array{success: bool, message_id: ?string, error?: string}
     */
    public function publish(
        string $channelChatId,
        string $contentType,
        ?string $text,
        ?string $fileId,
        string $platform = 'bale',
        ?string $botToken = null,
        ?string $localPath = null,
        ?string $fileName = null,
        ?string $mimeType = null,
        ?string $parseMode = null
    ): array;

    public function answerCallback(?string $callbackId, string $text = ''): void;
}
