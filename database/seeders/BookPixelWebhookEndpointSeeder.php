<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookPixelWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'book-pixel')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'book-pixel',
                'name' => 'یک پیکسل کتاب',
                'route' => 'api/webhook-book-pixel',
                'description' => 'ربات به اشتراک‌گذاری صفحات کتاب',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for Book Pixel Bot created successfully!');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for Book Pixel Bot already exists.');
        }
    }
}
