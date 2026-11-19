<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MpContactWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'webhook-mp-contact')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'webhook-mp-contact',
                'name' => 'ارتباط با نماینده مجلس',
                'route' => 'api/webhook-mp-contact',
                'description' => 'تیکتینگ مردمی، نظرسنجی موافق/مخالف و پنل ادمین داخل چت با دکمه‌های شیشه‌ای',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command?->info('✅ Webhook endpoint for MP Contact Bot created successfully!');
        } else {
            $this->command?->info('ℹ️  Webhook endpoint for MP Contact Bot already exists.');
        }
    }
}
