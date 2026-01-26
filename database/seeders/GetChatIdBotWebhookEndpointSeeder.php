<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GetChatIdBotWebhookEndpointSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // بررسی اینکه endpoint قبلاً وجود دارد یا نه
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'get-chat-id')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'get-chat-id',
                'name' => 'Get Chat ID Bot',
                'route' => 'api/webhook-bot-get-id',
                'description' => 'ربات دریافت شناسه چت - نمایش شناسه چت، کانال، گروه و اطلاعات پیام فوروارد شده',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => true,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for Get Chat ID Bot created successfully!');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for Get Chat ID Bot already exists.');
        }
    }
}
