<?php

namespace App\Interfaces\Services;

use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use Illuminate\Support\Collection;

interface ChannelPosterBotService
{
    public function isOwner(Bot $bot, string $chatId, string $origin): bool;

    public function claimOwnerIfEmpty(Bot $bot, string $chatId, string $origin): bool;

    public function parseChannelForward(?array $message): ?array;

    public function parseChannelTarget(?array $message): ?array;

    /**
     * Extract publishable content from an inbound message.
     *
     * Returns null when the message carries no publishable content.
     *
     * @return array{type: string, text: ?string, file_id: ?string, file_name: ?string, mime_type: ?string}|null
     *   type ∈ text|photo|video|voice|audio|audio_unsupported|document
     *   - `audio_unsupported` is returned (instead of `document`) when the
     *     source file is clearly an unsupported audio format, so the caller
     *     can reply with a specific error instead of silently dropping it.
     *   - `file_name` / `mime_type` are populated when the source platform
     *     provides them (audio/document) and must be preserved through
     *     download (MediaStore) and upload (MediaSender) to keep the real
     *     file name, extension and MIME intact.
     */
    public function extractMedia(?array $message): ?array;

    public function getActiveBaleDestination(int $botId): ?ChannelPosterDestination;

    public function hasAnyDestination(int $botId): bool;

    public function saveBaleDestination(int $botId, string $channelChatId, ?string $title, ?string $tag = null): ChannelPosterDestination;

    public function saveDestination(
        int $botId,
        string $platform,
        string $channelChatId,
        ?string $title,
        ?string $tag,
        ?string $botToken
    ): ChannelPosterDestination;

    public function assignTagToUntagged(int $botId, string $tag): int;

    public function hasUntagged(int $botId): bool;

    /**
     * @return Collection<int, ChannelPosterDestination>
     */
    public function resolveDestinations(int $botId, string $target): Collection;

    /**
     * @return Collection<int, ChannelPosterDestination>
     */
    public function resolveByTag(int $botId, string $tagKey): Collection;

    /**
     * Unique tag keys (empty string = untagged), each with a display label.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function listTagGroups(int $botId): array;

    public function displayTagLabel(?string $tag, ?string $fallbackTitle = null): string;

    public function getTagSetting(int $botId, string $tag): ?\App\Models\ChannelPosterTagSetting;

    public function setSignatureEnabled(int $botId, string $tag, bool $enabled): \App\Models\ChannelPosterTagSetting;

    public function updateChannelLink(int $destinationId, string $link): \App\Models\ChannelPosterDestination;

    /**
     * ذخیرهٔ تنظیمات معنایی امضا برای یک مقصد (wizard /sign).
     *
     * فقط دادهٔ معنایی ذخیره می‌شود — HTML/Markdown نهایی هرگز در دیتابیس نیست
     * (رندر در زمان انتشار توسط SignatureRenderer انجام می‌شود).
     *
     * @param string $link    URL عمومی کانال (http/https)
     * @param string|null $label برچسب نمایشی کاربر (null = برچسب پیش‌فرض پلتفرم)
     * @param string $type    یکی از SignatureRenderer::TYPE_*
     * @param bool $enabled   true = امضا فعال در پست‌ها؛ false = لینک ذخیره بدون امضا
     */
    public function updateSignatureSettings(
        int $destinationId,
        string $link,
        ?string $label,
        string $type,
        bool $enabled
    ): \App\Models\ChannelPosterDestination;

    /**
     * Build a signature block with channel links.
     *
     * - بدون $platform : فرمت قدیمی خودرسان (همه کانال‌ها، ترتیب تصادفی) —
     *                    خروجی دقیقاً همان فرمت legacy است (backward-compatible).
     * - با $platform    : فقط لینک خود آن مقصد، رندر شده با SignatureRenderer
     *                    (نوع/برچسب/وضعیتِ ذخیره‌شده در مقصد)؛ اگر امزا غیرفعال
     *                    باشد (signature_enabled = false)، خروجی خالی است.
     *
     * @return array{text: string, parse_mode: ?string, button: ?array}
     */
    public function buildSignature(int $botId, string $tag, ?string $platform = null): array;

    /**
     * Build the smart signature for ONE destination (CP-SMART-SIGNATURE-SETTINGS).
     *
     * - signature_settings = null → legacy behavior (same as buildSignature platform mode).
     * - smart settings        → policy engine (SignatureSettingsService) selects
     *   links, SignatureRenderer::renderMulti produces output.
     * - signature_enabled = false → hard disable, always empty output.
     * - $frozenSelection[destId] pins the random pick for queue retry stability.
     *
     * @param array<int|string, int[]> $frozenSelection destId => [destinationIds]
     * @return array{text: string, parse_mode: ?string, button: ?array, fallback: bool}
     */
    public function buildSignatureForDestination(
        \App\Models\ChannelPosterDestination $dest,
        ?array $frozenSelection = null
    ): array;

    /**
     * Compute the frozen random selection for all smart-random destinations of a tag
     * (CP-SMART-SIGNATURE-SETTINGS). Stored on the queue row at enqueue time so
     * retries render exactly the same signature.
     *
     * @return array<int|string, int[]> destId => [destinationIds]
     */
    public function freezeSignatureSelections(int $botId, string $tag): array;

    /**
     * Persist smart signature settings (normalized) for one destination
     * (CP-SMART-SIGNATURE-SETTINGS).
     *
     * @param array $settings raw or normalized smart settings payload
     */
    public function saveSmartSignatureSettings(
        int $destinationId,
        array $settings
    ): \App\Models\ChannelPosterDestination;

    /**
     * Calculate the next available queue slot for this bot+tag.
     * Respects 2 AM–9 AM quiet hours (Asia/Tehran).
     */
    public function getNextSlot(int $botId, string $tag): \Carbon\Carbon;

    /**
     * Count pending items in the queue for this bot+tag.
     */
    public function countPending(int $botId, string $tag): int;

    /**
     * Enqueue content for scheduled publishing.
     */
    public function enqueue(
        int $botId,
        string $tag,
        string $contentType,
        ?string $text,
        ?string $fileId,
        bool $signatureEnabled,
        \Carbon\Carbon $scheduledAt,
        ?string $ownerChatId = null,
        string $ownerOrigin = 'bale',
        ?int $contentId = null,
        ?string $mediaPath = null,
        ?string $fileName = null,
        ?string $mimeType = null,
        ?array $signatureSelection = null
    ): \App\Models\ChannelPosterQueue;

    /**
     * Log a publish result for a destination.
     */
    public function logPublish(
        int $botId,
        int $destinationId,
        string $platform,
        bool $success,
        ?string $messageId = null,
        ?string $error = null,
        ?int $queueId = null,
        ?string $postUrl = null,
        ?string $linkStatus = null,
        ?int $contentId = null
    ): \App\Models\ChannelPosterPublishLog;

    /**
     * Build a human-readable publish report from logs.
     */
    public function buildPublishReport(array $results, \Illuminate\Support\Collection $destinations): string;
}
