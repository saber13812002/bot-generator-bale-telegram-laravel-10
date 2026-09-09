<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterPublishLog;
use App\Models\ChannelPosterQueue;
use App\Models\ChannelPosterTagSetting;
use Carbon\Carbon;
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

    public function getTagSetting(int $botId, string $tag): ?ChannelPosterTagSetting
    {
        return ChannelPosterTagSetting::where('bot_id', $botId)
            ->where('tag', $tag)
            ->first();
    }

    public function setSignatureEnabled(int $botId, string $tag, bool $enabled): ChannelPosterTagSetting
    {
        return ChannelPosterTagSetting::updateOrCreate(
            ['bot_id' => $botId, 'tag' => $tag],
            ['signature_enabled' => $enabled]
        );
    }

    public function updateChannelLink(int $destinationId, string $link): ChannelPosterDestination
    {
        $destination = ChannelPosterDestination::findOrFail($destinationId);
        $destination->channel_link = $link;
        $destination->save();

        return $destination;
    }

    public function buildSignature(int $botId, string $tag): string
    {
        $destinations = $this->resolveByTag($botId, $tag);
        $withLinks = $destinations->filter(fn (ChannelPosterDestination $d) => !empty($d->channel_link));

        if ($withLinks->isEmpty()) {
            return '';
        }

        $items = $withLinks->values()->toArray();
        shuffle($items);

        $platformLabels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
        ];

        $lines = [];
        foreach ($items as $item) {
            $label = $platformLabels[$item['platform']] ?? $item['platform'];
            $lines[] = $label . ":\n" . $item['channel_link'];
        }

        return "\n\n" . implode("\n\n", $lines);
    }

    public function getNextSlot(int $botId, string $tag): Carbon
    {
        $tz = 'Asia/Tehran';
        $lastScheduled = ChannelPosterQueue::where('bot_id', $botId)
            ->where('tag', $tag)
            ->pending()
            ->orderBy('scheduled_at', 'desc')
            ->value('scheduled_at');

        if ($lastScheduled) {
            $next = Carbon::parse($lastScheduled)->addHours(2);
        } else {
            $next = Carbon::now($tz)->addHours(2);
        }

        $next = $next->setTimezone($tz);

        // Skip quiet hours: 2 AM – 9 AM Tehran time
        $hour = (int) $next->format('H');
        if ($hour >= 2 && $hour < 9) {
            $next = $next->copy()->setTime(9, 0, 0);
        }

        return $next;
    }

    public function countPending(int $botId, string $tag): int
    {
        return ChannelPosterQueue::where('bot_id', $botId)
            ->where('tag', $tag)
            ->pending()
            ->count();
    }

    public function enqueue(
        int $botId,
        string $tag,
        string $contentType,
        ?string $text,
        ?string $fileId,
        bool $signatureEnabled,
        Carbon $scheduledAt,
        ?string $ownerChatId = null,
        string $ownerOrigin = 'bale'
    ): ChannelPosterQueue {
        return ChannelPosterQueue::create([
            'bot_id' => $botId,
            'tag' => $tag,
            'content_type' => $contentType,
            'text' => $text,
            'file_id' => $fileId,
            'signature_enabled' => $signatureEnabled,
            'scheduled_at' => $scheduledAt,
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_chat_id' => $ownerChatId,
            'owner_origin' => $ownerOrigin,
        ]);
    }

    public function logPublish(
        int $botId,
        int $destinationId,
        string $platform,
        bool $success,
        ?string $messageId = null,
        ?string $error = null,
        ?int $queueId = null
    ): ChannelPosterPublishLog {
        return ChannelPosterPublishLog::create([
            'bot_id' => $botId,
            'queue_id' => $queueId,
            'destination_id' => $destinationId,
            'platform' => $platform,
            'success' => $success,
            'message_id' => $messageId,
            'error' => $error,
            'published_at' => now(),
        ]);
    }

    public function buildPublishReport(array $results, Collection $destinations): string
    {
        $platformLabels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
        ];

        $lines = [];
        foreach ($results as $result) {
            $dest = $destinations->firstWhere('id', $result['destination_id']);
            $platform = $platformLabels[$result['platform']] ?? $result['platform'];
            $title = $dest?->channel_title ?? $dest?->channel_chat_id ?? '';
            $icon = $result['success'] ? '✅' : '❌';
            $lines[] = "• {$platform} — {$title} {$icon}";
        }

        if (empty($lines)) {
            return trans('bot.channel_poster_publish_failed');
        }

        $header = trans('bot.channel_poster_report_header');

        return $header . "\n" . implode("\n", $lines);
    }
}
