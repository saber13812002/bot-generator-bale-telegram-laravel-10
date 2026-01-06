<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrayerBotWebhookEndpointSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // بررسی اینکه endpoint قبلاً وجود دارد یا نه
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'prayer-bot')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'prayer-bot',
                'name' => 'Prayer Qadha Bot',
                'route' => 'webhook-prayer-bot',
                'description' => 'Prayer Qadha Bot - Tracks and reports prayer Qadha records (Rakats)',
                'requires_bot_mother_id' => true,
                'requires_token' => false,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for Prayer Bot created successfully!');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for Prayer Bot already exists.');
        }
    }
}
