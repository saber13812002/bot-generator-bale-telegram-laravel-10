<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RssAdminBotWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'webhook-rss-admin')
            ->exists();

        if ($exists) {
            $this->command?->info('RSS Admin Bot webhook endpoint already exists.');

            return;
        }

        DB::table('webhook_endpoints')->insert([
            'endpoint_id' => 'webhook-rss-admin',
            'name' => 'ربات ادمین RSS',
            'route' => 'api/webhook-rss-admin',
            'description' => 'ثبت سریع فید RSS با انتخاب تگ — فقط ادمین',
            'requires_bot_mother_id' => false,
            'requires_token' => true,
            'requires_language' => false,
            'supports_multiple_languages' => false,
            'is_active' => 1,
            'usage_instructions' => "1. توکن جدید از بله/تلگرام بگیرید (بدون Bot Mother).\n2. php artisan rss-admin:register-webhook --token=TOKEN --origin=bale\n3. در Nova یک Bot با endpoint_id=webhook-rss-admin بسازید.\n4. /start → ثبت فید RSS → تگ → @username یا لینک فید",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('RSS Admin Bot webhook endpoint created.');
    }
}
