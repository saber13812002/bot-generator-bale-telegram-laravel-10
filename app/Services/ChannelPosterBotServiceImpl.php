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

    public function claimOwnerIfEmpty(Bot $bot, string $chatId, string $origin): bool
    {
        $field = $origin === 'bale' ? 'bale_owner_chat_id' : 'telegram_owner_chat_id';
        $current = $bot->{$field};
        if ($current !== null && $current !== '') {
            return false;
        }

        $bot->{$field} = $chatId;
        $bot->save();

        return true;
    }

    public function parseChannelForward(?array $message): ?array
    {
        if (!$message) {
            return null;
        }

        $candidates = [];

        if (is_array($message['forward_from_chat'] ?? null)) {
            $candidates[] = $message['forward_from_chat'];
        }

        $origin = $message['forward_origin'] ?? null;
        if (is_array($origin)) {
            if (is_array($origin['chat'] ?? null)) {
                $candidates[] = $origin['chat'];
            }
            if (isset($origin['chat_id'])) {
                $candidates[] = [
                    'id' => $origin['chat_id'],
                    'title' => $origin['author_signature'] ?? null,
                    'type' => ($origin['type'] ?? '') === 'channel' ? 'channel' : ($origin['type'] ?? ''),
                ];
            }
        }

        $externalOrigin = $message['external_reply']['origin'] ?? null;
        if (is_array($externalOrigin)) {
            if (is_array($externalOrigin['chat'] ?? null)) {
                $candidates[] = $externalOrigin['chat'];
            }
            if (isset($externalOrigin['chat_id'])) {
                $candidates[] = [
                    'id' => $externalOrigin['chat_id'],
                    'title' => $externalOrigin['author_signature'] ?? null,
                    'type' => $externalOrigin['type'] ?? '',
                ];
            }
        }

        if (is_array($message['sender_chat'] ?? null)) {
            $candidates[] = $message['sender_chat'];
        }

        $replyForward = $message['reply_to_message']['forward_from_chat'] ?? null;
        if (is_array($replyForward)) {
            $candidates[] = $replyForward;
        }

        $forwardFrom = $message['forward_from'] ?? null;
        if (is_array($forwardFrom) && isset($forwardFrom['id'])) {
            $looksLikeUser = isset($forwardFrom['first_name']) || isset($forwardFrom['is_bot']);
            $looksLikeChat = isset($forwardFrom['title']) || ($forwardFrom['type'] ?? '') === 'channel';
            if ($looksLikeChat || !$looksLikeUser) {
                $candidates[] = $forwardFrom;
            }
        }

        foreach ($candidates as $chat) {
            $parsed = $this->chatToChannelTarget($chat);
            if ($parsed) {
                return $parsed;
            }
        }

        return null;
    }

    public function parseChannelTarget(?array $message): ?array
    {
        $fromForward = $this->parseChannelForward($message);
        if ($fromForward) {
            return $fromForward;
        }

        $text = trim((string) ($message['text'] ?? ''));
        if (preg_match('/^-?\d{3,}$/', $text)) {
            return [
                'id' => $text,
                'title' => null,
                'type' => 'id',
            ];
        }

        return null;
    }

    private function chatToChannelTarget(array $chat): ?array
    {
        if (!isset($chat['id'])) {
            return null;
        }

        $type = $chat['type'] ?? '';
        if ($type === 'private') {
            return null;
        }

        return [
            'id' => (string) $chat['id'],
            'title' => $chat['title'] ?? $chat['username'] ?? null,
            'type' => $type !== '' ? $type : 'channel',
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

    public function hasAnyDestination(int $botId): bool
    {
        return ChannelPosterDestination::where('bot_id', $botId)
            ->where('is_active', true)
            ->exists();
    }

    public function saveBaleDestination(int $botId, string $channelChatId, ?string $title, ?string $tag = null): ChannelPosterDestination
    {
        return $this->saveDestination(
            $botId,
            ChannelPosterDestination::PLATFORM_BALE,
            $channelChatId,
            $title,
            $tag,
            null
        );
    }

    public function saveDestination(
        int $botId,
        string $platform,
        string $channelChatId,
        ?string $title,
        ?string $tag,
        ?string $botToken
    ): ChannelPosterDestination {
        $tag = $tag !== null ? trim($tag) : null;
        if ($tag === '') {
            $tag = null;
        }

        return ChannelPosterDestination::updateOrCreate(
            [
                'bot_id' => $botId,
                'platform' => $platform,
                'channel_chat_id' => $channelChatId,
            ],
            [
                'channel_title' => $title,
                'tag' => $tag,
                'bot_token' => $botToken,
                'verified_at' => now(),
                'is_active' => true,
            ]
        );
    }

    public function assignTagToUntagged(int $botId, string $tag): int
    {
        $tag = trim($tag);
        if ($tag === '') {
            return 0;
        }

        return ChannelPosterDestination::where('bot_id', $botId)
            ->where(function ($query) {
                $query->whereNull('tag')->orWhere('tag', '');
            })
            ->update(['tag' => $tag]);
    }

    public function hasUntagged(int $botId): bool
    {
        return ChannelPosterDestination::where('bot_id', $botId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('tag')->orWhere('tag', '');
            })
            ->exists();
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

    public function resolveByTag(int $botId, string $tagKey): Collection
    {
        $query = ChannelPosterDestination::where('bot_id', $botId)
            ->where('is_active', true);

        if ($tagKey === '') {
            $query->where(function ($inner) {
                $inner->whereNull('tag')->orWhere('tag', '');
            });
        } else {
            $query->where('tag', $tagKey);
        }

        return $query->get();
    }

    public function listTagGroups(int $botId): array
    {
        $destinations = ChannelPosterDestination::where('bot_id', $botId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $groups = [];
        foreach ($destinations as $destination) {
            $key = trim((string) $destination->tag);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $this->displayTagLabel($destination->tag, $destination->channel_title),
                ];
            }
        }

        return array_values($groups);
    }

    public function displayTagLabel(?string $tag, ?string $fallbackTitle = null): string
    {
        $tag = trim((string) $tag);
        if ($tag !== '') {
            return $tag;
        }

        $fallbackTitle = trim((string) $fallbackTitle);
        if ($fallbackTitle !== '') {
            return $fallbackTitle;
        }

        return trans('bot.channel_poster_untagged');
    }
}
