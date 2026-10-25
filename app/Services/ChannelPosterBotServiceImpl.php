<?php

namespace App\Services;

use App\Helpers\ProductLinkMessageHelper;
use App\Interfaces\Services\ChannelPosterBotService;
use App\Services\ChannelPoster\SignatureRenderer;
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
        // Phase 5: config-driven — ستون owner از رجیستری sync_platforms
        // (قبل از Phase 5، soroush/rubika اشتباهاً ستون telegram را می‌خواندند).
        $ownerChatId = $bot->{SyncPlatformRegistry::ownerColumn($origin)};

        if ($ownerChatId === null || $ownerChatId === '') {
            return false;
        }

        return (string) $ownerChatId === (string) $chatId;
    }

    public function claimOwnerIfEmpty(Bot $bot, string $chatId, string $origin): bool
    {
        $field = SyncPlatformRegistry::ownerColumn($origin);
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
                    'file_name' => null,
                    'mime_type' => null,
                ];
            }
        }

        if (!empty($message['video']['file_id'])) {
            return [
                'type' => 'video',
                'text' => $caption,
                'file_id' => $message['video']['file_id'],
                'file_name' => null,
                'mime_type' => null,
            ];
        }

        if (!empty($message['voice']['file_id'])) {
            return [
                'type' => 'voice',
                'text' => $caption,
                'file_id' => $message['voice']['file_id'],
                'file_name' => null,
                'mime_type' => null,
            ];
        }

        if (!empty($message['audio']['file_id'])) {
            $audio = $message['audio'];

            return [
                'type' => 'audio',
                'text' => $caption,
                'file_id' => $message['audio']['file_id'],
                'file_name' => is_array($audio) ? ($audio['file_name'] ?? null) : null,
                'mime_type' => is_array($audio) ? ($audio['mime_type'] ?? null) : null,
            ];
        }

        // بله/تلگرام ممکن است MP3 (و سایر فایل‌ها) را به‌جای message.audio در
        // message.document بفرستند. طبق سیاست محصول:
        //  1) اگر MIME معتبر audio/mpeg باشد → نوع audio (معتبرترین نشانه)
        //  2) وگرنه اگر پسوند معتبر .mp3 باشد و MIME به‌وضوح نوع دیگری نباشد
        //     (مثلا text/* یا image/* یا application/pdf) → نوع audio
        //  3) اگر audio/* باشد ولی نه mpeg/MP3 → audio_unsupported
        //     (خطای مشخص به کاربر، هرگز بی‌صدا)
        //  4) در غیر این صورت → نوع document (سند عمومی)
        if (is_array($message['document'] ?? null)) {
            $doc = $message['document'];
            $fileId = $doc['file_id'] ?? null;

            if ($fileId) {
                $fileName = $doc['file_name'] ?? null;
                $docMime = $doc['mime_type'] ?? null;

                $normalizedMime = is_string($docMime) ? strtolower(trim($docMime)) : '';
                $docExt = $this->documentExtension($fileName);

                // MIME که به‌وضوح نوع محتوای غیرصوتی را نشان می‌دهد؛ در این
                // صورت به پسوند سمت کلاینت اعتماد نمی‌کنیم (تغییرنام کاذب).
                $mimeIsOtherKind = str_starts_with($normalizedMime, 'text/')
                    || str_starts_with($normalizedMime, 'image/')
                    || str_starts_with($normalizedMime, 'video/')
                    || (str_starts_with($normalizedMime, 'application/') && $normalizedMime !== 'application/octet-stream');

                $isAudio = $normalizedMime === 'audio/mpeg'
                    || ($docExt === 'mp3' && !$mimeIsOtherKind);

                if ($isAudio) {
                    return [
                        'type' => 'audio',
                        'text' => $caption,
                        'file_id' => $fileId,
                        'file_name' => is_string($fileName) ? $fileName : null,
                        'mime_type' => $normalizedMime !== '' ? $normalizedMime : null,
                    ];
                }

                // فایل صوتی با قالب ناپشتیبانی‌شده: به‌جای بی‌صدا رد کردن،
                // نوع مشخصی برمی‌گردانیم تا پیام خطای مشخصی ارسال شود.
                if (str_starts_with($normalizedMime, 'audio/')) {
                    return [
                        'type' => 'audio_unsupported',
                        'text' => $caption,
                        'file_id' => $fileId,
                        'file_name' => is_string($fileName) ? $fileName : null,
                        'mime_type' => $normalizedMime,
                    ];
                }

                return [
                    'type' => 'document',
                    'text' => $caption,
                    'file_id' => $fileId,
                    'file_name' => is_string($fileName) ? $fileName : null,
                    'mime_type' => $normalizedMime !== '' ? $normalizedMime : null,
                ];
            }
        }

        $text = $message['text'] ?? null;
        if (is_string($text) && $text !== '' && !str_starts_with($text, '/')) {
            return [
                'type' => 'text',
                'text' => $text,
                'file_id' => null,
                'file_name' => null,
                'mime_type' => null,
            ];
        }

        return null;
    }

    /**
     * استخراج پسوند امن از نام فایل (حروف کوچک، 1 تا 5 حرف اعداد/حروف).
     *
     * @return string|null
     */
    private function documentExtension(?string $fileName): ?string
    {
        if (!is_string($fileName) || $fileName === '') {
            return null;
        }

        // از مسیر کامل فقط بخش نام فایل را در نظر بگیر
        $base = basename(str_replace('\\', '/', $fileName));
        $dot = strrpos($base, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($base) - 1) {
            return null;
        }

        $ext = strtolower(substr($base, $dot + 1));
        if (preg_match('/^[a-z0-9]{1,5}$/', $ext) !== 1) {
            return null;
        }

        return $ext;
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

    public function updateSignatureSettings(
        int $destinationId,
        string $link,
        ?string $label,
        string $type,
        bool $enabled
    ): ChannelPosterDestination {
        $destination = ChannelPosterDestination::findOrFail($destinationId);
        $destination->channel_link = $link;
        $destination->signature_label = $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 255) : null;
        $destination->signature_type = $type;
        $destination->signature_enabled = $enabled;
        $destination->save();

        return $destination;
    }

    public function buildSignature(int $botId, string $tag, ?string $platform = null): array
    {
        $empty = ['text' => '', 'parse_mode' => null, 'button' => null];

        $destinations = $this->resolveByTag($botId, $tag);
        $withLinks = $destinations->filter(fn (ChannelPosterDestination $d) => !empty($d->channel_link));

        if ($withLinks->isEmpty()) {
            return $empty;
        }

        $items = $withLinks->values()->toArray();

        $platformLabels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
            ChannelPosterDestination::PLATFORM_RUBIKA => trans('bot.channel_poster_sig_rubika'),
        ];

        // ── حالت پلتفرم‌آگاه: فقط لینک خود همان مقصد، رندر شده با SignatureRenderer ──
        // امزا فقط وقتی ساخته می‌شود که فعال باشد:
        //   signature_enabled = null  → legacy (قدیمی: لینک ذخیره ⇒ فعال)
        //   signature_enabled = true  → فعال (ویزارد جدید)
        //   signature_enabled = false → غیرفعال (ویزارد جدید)
        if ($platform !== null && $platform !== '') {
            $dest = null;
            foreach ($items as $item) {
                if ($item['platform'] === $platform) {
                    $dest = $item;
                    break;
                }
            }

            if ($dest === null || $dest['signature_enabled'] === false) {
                return $empty;
            }

            $type = in_array($dest['signature_type'] ?? null, [
                SignatureRenderer::TYPE_INLINE,
                SignatureRenderer::TYPE_RAW,
                SignatureRenderer::TYPE_PLAIN,
                SignatureRenderer::TYPE_BUTTON,
                SignatureRenderer::TYPE_COMPOSITE,
                SignatureRenderer::TYPE_NONE,
            ], true) ? (string) $dest['signature_type'] : SignatureRenderer::TYPE_INLINE;

            // برچسب کاربر (ویزارد جدید) یا برچسب پیش‌فرض پلتفرم (legacy)
            $label = !empty($dest['signature_label'])
                ? (string) $dest['signature_label']
                : ($platformLabels[$platform] ?? $platform);

            $rendered = SignatureRenderer::render($platform, (string) $dest['channel_link'], $type, $label);

            return [
                'text' => $rendered['text'],
                'parse_mode' => $rendered['parse_mode'],
                'button' => $rendered['button'],
            ];
        }

        // ── حالت قدیمی (مقصد نامشخص): همه کانال‌ها، خودرسان، ترتیب تصادفی ──
        // فرمت دقیقاً هم‌سان خروجی legacy (renderLegacy) تا رفتار تغییر نکند؛
        // مقصدهای صریح‌غیرفعال (signature_enabled = false) حذف می‌شوند.
        shuffle($items);
        $lines = [];
        foreach ($items as $item) {
            if ($item['signature_enabled'] === false) {
                continue;
            }

            $label = $platformLabels[$item['platform']] ?? $item['platform'];
            $lines[] = SignatureRenderer::renderLegacy($item['platform'], $item['channel_link'], $label);
        }

        if ($lines === []) {
            return $empty;
        }

        return ['text' => "\n\n" . implode("\n\n", $lines), 'parse_mode' => null, 'button' => null];
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
        string $ownerOrigin = 'bale',
        ?int $contentId = null,
        ?string $mediaPath = null,
        ?string $fileName = null,
        ?string $mimeType = null
    ): ChannelPosterQueue {
        return ChannelPosterQueue::create([
            'bot_id' => $botId,
            'tag' => $tag,
            'content_type' => $contentType,
            'text' => $text,
            'file_id' => $fileId,
            'media_path' => $mediaPath,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'signature_enabled' => $signatureEnabled,
            'scheduled_at' => $scheduledAt,
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_chat_id' => $ownerChatId,
            'owner_origin' => $ownerOrigin,
            'content_id' => $contentId,
        ]);
    }

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
            'post_url' => $postUrl,
            'link_status' => $linkStatus,
            'content_id' => $contentId,
        ]);
    }

    public function buildPublishReport(array $results, Collection $destinations): string
    {
        $platformLabels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
            ChannelPosterDestination::PLATFORM_RUBIKA => trans('bot.channel_poster_sig_rubika'),
        ];

        $lines = [];
        foreach ($results as $result) {
            $dest = $destinations->firstWhere('id', $result['destination_id']);
            $platform = $platformLabels[$result['platform']] ?? $result['platform'];
            $title = $dest?->channel_title ?? $dest?->channel_chat_id ?? '';
            $icon = $result['success'] ? '✅' : '❌';
            $line = "• {$platform} — {$title} {$icon}";

            // دلیل شکست (مثلا‌ خطای آپلود رسانه) برای شفافیت
            $error = $result['error'] ?? null;
            if (!$result['success'] && is_string($error) && trim($error) !== '') {
                $line .= "\n   ↳ " . mb_substr(trim($error), 0, 200);
            }

            $lines[] = $line;
        }

        if (empty($lines)) {
            return trans('bot.channel_poster_publish_failed');
        }

        $header = trans('bot.channel_poster_report_header');

        return $header . "\n" . implode("\n", $lines);
    }
}
