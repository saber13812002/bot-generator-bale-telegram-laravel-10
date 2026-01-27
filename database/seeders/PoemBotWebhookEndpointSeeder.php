<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PoemBotWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'poem-bot')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'poem-bot',
                'name' => 'ربات شعر و موسیقی',
                'route' => 'api/webhook-poem-bot',
                'description' => 'ربات برای ارسال، ویرایش، نسخه‌بندی و لایک اشعار',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for Poem Bot created successfully!');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for Poem Bot already exists.');
        }
    }
}
