<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureEitaaRssChannel extends Command
{
    protected $signature = 'app:ensure-eitaa-rss-channel
                            {--channel-id=2 : rss_channels.id for Eitaa}
                            {--target-id=8419225 : Eitaa channel chat_id}
                            {--token= : توکن ربات ایتا (پیش‌فرض: env یا --token)}
                            {--dry-run : فقط نمایش، بدون ذخیره}';

    protected $description = 'ایجاد/به‌روزرسانی rss_channel ایتا از BOT_EITAA_TOKEN_SABER در .env';

    public function handle(): int
    {
        $token = $this->resolveEitaaToken();
        if (!$token) {
            $this->error('توکن ایتا پیدا نشد. BOT_EITAA_TOKEN_SABER را در .env بگذارید یا --token= بدهید.');
            return self::FAILURE;
        }

        $originId = DB::table('rss_channel_origins')->where('slug', 'eitaa')->value('id');
        if (!$originId) {
            if ($this->option('dry-run')) {
                $this->warn('rss_channel_origins slug=eitaa وجود ندارد — در اجرای واقعی ایجاد می‌شود.');
                $originId = 5;
            } else {
                $originId = DB::table('rss_channel_origins')->insertGetId([
                    'name' => 'Eitaa',
                    'slug' => 'eitaa',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("rss_channel_origins eitaa ایجاد شد (id={$originId})");
            }
        }

        $channelId = (int) $this->option('channel-id');
        $payload = [
            'origin_id' => $originId,
            'title' => 'eitaa log pardisania',
            'slug' => 'eitaalogpardisania',
            'token' => $token,
            'target_id' => (string) $this->option('target-id'),
            'type' => 'channel',
            'updated_at' => now(),
        ];

        if ($this->option('dry-run')) {
            $this->info("Would upsert rss_channels.id={$channelId}:");
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $exists = DB::table('rss_channels')->where('id', $channelId)->exists();
        if ($exists) {
            DB::table('rss_channels')->where('id', $channelId)->update($payload);
            $this->info("rss_channels id={$channelId} به‌روزرسانی شد.");
        } else {
            DB::table('rss_channels')->insert(array_merge(['id' => $channelId, 'created_at' => now()], $payload));
            $this->info("rss_channels id={$channelId} ایجاد شد.");
        }

        $this->line("target_id={$payload['target_id']} | token=" . substr($token, 0, 12) . '...');

        return self::SUCCESS;
    }

    private function resolveEitaaToken(): ?string
    {
        $override = $this->option('token');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        foreach (['BOT_EITAA_TOKEN_SABER', 'EITAA_BOT_TOKEN', 'BOT_EITAA_TOKEN'] as $key) {
            $value = env($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
