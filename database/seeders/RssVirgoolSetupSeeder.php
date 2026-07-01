<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RssVirgoolSetupSeeder extends Seeder
{
    public function run(): void
    {
        $tagId = $this->ensureVirgoolTag();
        $this->attachTagToRssChannels($tagId);
        $rssItemId = $this->ensureSamiusblytheFeed();
        $this->attachTagToRssItem($tagId, $rssItemId);

        $this->command?->info('Virgool tag, channel tags, and samiusblythe feed are ready.');
    }

    private function ensureVirgoolTag(): int
    {
        $existing = DB::table('tags')
            ->where('slug', 'like', '%virgool%')
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $maxOrder = (int) DB::table('tags')->max('order_column');

        return (int) DB::table('tags')->insertGetId([
            'name' => json_encode(['fa' => 'virgool'], JSON_UNESCAPED_UNICODE),
            'slug' => json_encode(['fa' => 'virgool'], JSON_UNESCAPED_UNICODE),
            'type' => null,
            'order_column' => $maxOrder + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachTagToRssChannels(int $tagId): void
    {
        $channelIds = DB::table('rss_channels')->pluck('id');

        foreach ($channelIds as $channelId) {
            $exists = DB::table('taggables')
                ->where('tag_id', $tagId)
                ->where('taggable_type', 'App\\Models\\RssChannel')
                ->where('taggable_id', $channelId)
                ->exists();

            if (!$exists) {
                DB::table('taggables')->insert([
                    'tag_id' => $tagId,
                    'taggable_type' => 'App\\Models\\RssChannel',
                    'taggable_id' => $channelId,
                ]);
            }
        }
    }

    private function ensureSamiusblytheFeed(): int
    {
        $url = 'https://virgool.io/feed/@samiusblythe';
        $existing = DB::table('rss_items')->where('url', $url)->first();

        if ($existing) {
            DB::table('rss_items')->where('id', $existing->id)->update([
                'title' => 'samiusblythe virgool',
                'description' => 'samiusblythe virgool',
                'is_active' => 1,
                'unique_xml_tag' => 'link',
                'locale' => 'fa',
                'target_locale' => 'fa',
                'interval_minutes' => 60,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('rss_items')->insertGetId([
            'title' => 'samiusblythe virgool',
            'description' => 'samiusblythe virgool',
            'url' => $url,
            'url_ifttt' => null,
            'url_rss_dot_app' => null,
            'is_active' => 1,
            'unique_xml_tag' => 'link',
            'locale' => 'fa',
            'target_locale' => 'fa',
            'rss_channel_id' => 1,
            'interval_minutes' => 60,
            'rss_business_id' => 2,
            'last_synced_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachTagToRssItem(int $tagId, int $rssItemId): void
    {
        $exists = DB::table('taggables')
            ->where('tag_id', $tagId)
            ->where('taggable_type', 'App\\Models\\RssItem')
            ->where('taggable_id', $rssItemId)
            ->exists();

        if (!$exists) {
            DB::table('taggables')->insert([
                'tag_id' => $tagId,
                'taggable_type' => 'App\\Models\\RssItem',
                'taggable_id' => $rssItemId,
            ]);
        }
    }
}
