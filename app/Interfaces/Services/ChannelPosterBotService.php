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
}
