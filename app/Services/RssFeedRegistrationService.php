<?php

namespace App\Services;

use App\Models\RssItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RssFeedRegistrationService
{
    public function normalizeFeedUrl(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match('#^https?://(?:www\.)?virgool\.io/feed/@([a-zA-Z0-9_.-]+)/?#i', $input, $matches)) {
            return 'https://virgool.io/feed/@' . $matches[1];
        }

        if (preg_match('#^https?://(?:www\.)?virgool\.io/@([a-zA-Z0-9_.-]+)/?#i', $input, $matches)) {
            return 'https://virgool.io/feed/@' . $matches[1];
        }

        if (preg_match('#^@?([a-zA-Z0-9_.-]+)$#', $input, $matches)) {
            return 'https://virgool.io/feed/@' . $matches[1];
        }

        if (preg_match('#^https?://#i', $input)) {
            return $input;
        }

        return null;
    }

    /**
     * @return array{ok: bool, title?: string, error?: string}
     */
    public function validateRssFeed(string $url): array
    {
        try {
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                return [
                    'ok' => false,
                    'error' => 'fetch_failed',
                ];
            }

            $xml = @simplexml_load_string($response->body());
            if ($xml === false || !isset($xml->channel)) {
                return [
                    'ok' => false,
                    'error' => 'invalid_xml',
                ];
            }

            $title = isset($xml->channel->title)
                ? trim(strip_tags((string) $xml->channel->title))
                : '';

            if (!isset($xml->channel->item)) {
                return [
                    'ok' => false,
                    'error' => 'no_items',
                ];
            }

            return [
                'ok' => true,
                'title' => $title !== '' ? $title : $url,
            ];
        } catch (\Throwable $e) {
            Log::warning('RSS feed validation failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'exception',
            ];
        }
    }

    /**
     * @return array{ok: bool, rss_item_id?: int, error?: string, duplicate?: bool}
     */
    public function registerFeed(string $url, string $tagName, int $rssBusinessId = 2, bool $fetchNow = true): array
    {
        $normalizedUrl = $this->normalizeFeedUrl($url);
        if ($normalizedUrl === null) {
            return ['ok' => false, 'error' => 'invalid_url'];
        }

        $existing = RssItem::query()->where('url', $normalizedUrl)->first();
        if ($existing) {
            return [
                'ok' => false,
                'error' => 'duplicate',
                'rss_item_id' => $existing->id,
                'duplicate' => true,
            ];
        }

        $validation = $this->validateRssFeed($normalizedUrl);
        if (!$validation['ok']) {
            return [
                'ok' => false,
                'error' => $validation['error'] ?? 'invalid_feed',
            ];
        }

        $rssItem = new RssItem();
        $rssItem->title = $validation['title'] ?? $normalizedUrl;
        $rssItem->description = $rssItem->title;
        $rssItem->url = $normalizedUrl;
        $rssItem->is_active = true;
        $rssItem->unique_xml_tag = 'link';
        $rssItem->locale = 'fa';
        $rssItem->target_locale = 'fa';
        $rssItem->rss_channel_id = 1;
        $rssItem->interval_minutes = 60;
        $rssItem->rss_business_id = $rssBusinessId;
        $rssItem->last_synced_at = now()->subDay();
        $rssItem->save();

        $rssItem->attachTag($tagName, 'fa');

        if ($fetchNow) {
            try {
                RssService::readRssAndSave($normalizedUrl, $rssItem->id, 'link');
                $rssItem->last_synced_at = now();
                $rssItem->save();
            } catch (\Throwable $e) {
                Log::warning('Immediate RSS fetch after registration failed', [
                    'rss_item_id' => $rssItem->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'ok' => true,
            'rss_item_id' => $rssItem->id,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getChannelTags(string $locale = 'fa'): array
    {
        return \App\Models\RssChannel::query()
            ->with('tags')
            ->get()
            ->flatMap(fn ($channel) => $channel->tags)
            ->unique('id')
            ->map(function ($tag) use ($locale) {
                $name = $tag->getTranslation('name', $locale, false)
                    ?: $tag->getTranslation('name', 'fa', false)
                    ?: (string) $tag->name;

                return [
                    'id' => $tag->id,
                    'name' => $name,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, url: string, is_active: bool}>
     */
    public function listActiveFeeds(int $limit = 30): array
    {
        return RssItem::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'title', 'url', 'is_active'])
            ->map(fn (RssItem $item) => [
                'id' => $item->id,
                'title' => $item->title ?? ('Feed #' . $item->id),
                'url' => $item->url,
                'is_active' => (bool) $item->is_active,
            ])
            ->all();
    }
}
