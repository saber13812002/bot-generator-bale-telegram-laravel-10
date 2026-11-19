<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChannelPosterWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'webhook-channel-poster')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'webhook-channel-poster',
                'name' => 'ارسال به کانال‌ها',
                'route' => 'api/webhook-channel-poster',
                'description' => 'ارسال متن، عکس، صوت و ویدیو از خصوصی به کانال بله (تلگرام و ایتا در فازهای بعد)',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command?->info('Webhook endpoint for Channel Poster Bot created successfully.');
        } else {
            $this->command?->info('Webhook endpoint for Channel Poster Bot already exists.');
        }
    }
}
