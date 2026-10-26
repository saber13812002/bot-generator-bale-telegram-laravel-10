<?php

namespace App\Interfaces\Services;

interface ChannelPosterPublisher
{
    /**
     * Classes of the result of sendTestMessage (CP-RUBIKA-RESPONSE-FIX / M2+M4).
     *
     * HTTP 200 alone is not success; missing message_id alone is not failure —
     * the class reflects the real evidence in the API response.
     */
    public const TEST_SEND_SUCCESS_WITH_MESSAGE_ID = 'success_with_message_id';
    public const TEST_SEND_SUCCESS_WITHOUT_MESSAGE_ID = 'success_without_message_id';
    public const TEST_SEND_API_REJECTED = 'api_rejected';
    public const TEST_SEND_TRANSPORT_FAILURE = 'transport_failure';
    public const TEST_SEND_UNKNOWN_RESULT = 'unknown_result';
    public const TEST_SEND_INVALID_INPUT = 'invalid_input';

    public function sendPrivateMessage(string $chatId, string $text, ?array $inlineKeyboardRows = null): void;

    /**
     * ارسال پیام تست (واریز /add).
     *
     * @return array{
     *     ok: bool,
     *     class: string,             یکی از TEST_SEND_*
     *     message_id: ?string,
     *     error: ?string,
     *     http_status: ?int,
     *     permission_denied: bool   شواهد واقعی عدم دسترسی (403/forbidden)
     * }
     */
    public function sendTestMessage(string $channelChatId, string $text, string $platform = 'bale', ?string $botToken = null): array;

    /**
     * @param string|null $localPath مسیر مطلق فایل رسانه‌ای روی دیسک (برای آپلود multipart؛
     *                              به‌جای file_id که فقط برای پلتفرم مبدأ معتبر است)
     * @param array|null $replyMarkup markup دکمهٔ inline (inline_keyboard) — فقط برای
     *                               انتشار متنی در پلتفرم‌های پشتیبانی‌کننده (telegram/bale).
     *                               بقیه پلتفرم‌ها نادیده می‌گیرند (log در native publisher).
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
        ?string $parseMode = null,
        ?array $replyMarkup = null
    ): array;

    public function answerCallback(?string $callbackId, string $text = ''): void;
}
