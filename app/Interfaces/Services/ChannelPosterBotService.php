<?php

namespace App\Interfaces\Services;

use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use Illuminate\Support\Collection;

interface ChannelPosterBotService
{
    public function isOwner(Bot $bot, string $chatId, string $origin): bool;

    public function parseChannelForward(?array $message): ?array;

    /**
     * Forwarded channel/group chat, or a pasted numeric chat id.
     */
    public function parseChannelTarget(?array $message): ?array;

    public function extractMedia(?array $message): ?array;

    public function getActiveBaleDestination(int $botId): ?ChannelPosterDestination;

    public function saveBaleDestination(int $botId, string $channelChatId, ?string $title): ChannelPosterDestination;

    /**
     * @return Collection<int, ChannelPosterDestination>
     */
    public function resolveDestinations(int $botId, string $target): Collection;
}
