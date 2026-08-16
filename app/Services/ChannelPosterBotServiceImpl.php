<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use Illuminate\Support\Collection;

class ChannelPosterBotServiceImpl implements ChannelPosterBotService
{
    public function isOwner(Bot $bot, string $chatId, string $origin): bool
    {
        $ownerChatId = $origin === 'bale'
            ? $bot->bale_owner_chat_id
            : $bot->telegram_owner_chat_id;

        if ($ownerChatId === null || $ownerChatId === '') {
            return false;
        }

        return (string) $ownerChatId === (string) $chatId;
    }

    public function parseChannelForward(?array $message): ?array
    {
        if (!$message) {
            return null;
        }

        $forwardFromChat = $message['forward_from_chat'] ?? null;
        if (!is_array($forwardFromChat) || !isset($forwardFromChat['id'])) {
            return null;
        }

        $type = $forwardFromChat['type'] ?? '';
        if ($type !== 'channel') {
            return null;
        }

        return [
            'id' => (string) $forwardFromChat['id'],
            'title' => $forwardFromChat['title'] ?? null,
            'type' => 'channel',
        ];
    }

    public function extractMedia(?array $message): ?array
    {
        if (!$message) {
            return null;
        }

        $caption = $message['caption'] ?? null;

        if (!empty($message['photo']) && is_array($message['photo'])) {
            $photos = $message['photo'];
            $last = $photos[count($photos) - 1] ?? null;
            $fileId = is_array($last) ? ($last['file_id'] ?? null) : null;
            if ($fileId) {
                return [
                    'type' => 'photo',
                    'text' => $caption,
                    'file_id' => $fileId,
                ];
            }
        }

        if (!empty($message['video']['file_id'])) {
            return [
                'type' => 'video',
                'text' => $caption,
                'file_id' => $message['video']['file_id'],
            ];
        }

        if (!empty($message['voice']['file_id'])) {
            return [
                'type' => 'voice',
                'text' => $caption,
                'file_id' => $message['voice']['file_id'],
            ];
        }

        if (!empty($message['audio']['file_id'])) {
            return [
                'type' => 'audio',
                'text' => $caption,
                'file_id' => $message['audio']['file_id'],
            ];
        }

        $text = $message['text'] ?? null;
        if (is_string($text) && $text !== '' && !str_starts_with($text, '/')) {
            return [
                'type' => 'text',
                'text' => $text,
                'file_id' => null,
            ];
        }

        return null;
    }

    public function getActiveBaleDestination(int $botId): ?ChannelPosterDestination
    {
        return ChannelPosterDestination::where('bot_id', $botId)
            ->where('platform', ChannelPosterDestination::PLATFORM_BALE)
            ->where('is_active', true)
            ->first();
    }

    public function saveBaleDestination(int $botId, string $channelChatId, ?string $title): ChannelPosterDestination
    {
        return ChannelPosterDestination::updateOrCreate(
            [
                'bot_id' => $botId,
                'platform' => ChannelPosterDestination::PLATFORM_BALE,
            ],
            [
                'channel_chat_id' => $channelChatId,
                'channel_title' => $title,
                'bot_token' => null,
                'verified_at' => now(),
                'is_active' => true,
            ]
        );
    }

    public function resolveDestinations(int $botId, string $target): Collection
    {
        $query = ChannelPosterDestination::where('bot_id', $botId)
            ->where('is_active', true);

        if ($target === ChannelPosterDestination::PLATFORM_BALE) {
            $query->where('platform', ChannelPosterDestination::PLATFORM_BALE);
        }

        return $query->get();
    }
}
