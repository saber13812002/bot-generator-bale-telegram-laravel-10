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
     * Build a signature block with channel links.
     *
     * - بدون $platform : فرمت قدیمی خودرسان (همه کانال‌ها، ترتیب تصادفی).
     * - با $platform    : فقط لینک خود آن مقصد، با نگارش (parse) مخصوص پلتفرم.
     *
     * @return array{text: string, parse_mode: ?string}
     */
    public function buildSignature(int $botId, string $tag, ?string $platform = null): array;

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
        ?string $mimeType = null
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
