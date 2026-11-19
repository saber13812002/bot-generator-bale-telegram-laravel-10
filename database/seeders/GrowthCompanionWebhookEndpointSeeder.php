<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GrowthCompanionWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'webhook-growth-companion')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'webhook-growth-companion',
                'name' => 'رشدیار',
                'route' => 'api/webhook-growth-companion',
                'description' => 'موتور رشد شخصی قابل‌تنظیم: سؤال روزانه، تأمل و برنامه جدا برای هر حوزه زندگی',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command?->info('Webhook endpoint for Growth Companion created successfully.');
        } else {
            $this->command?->info('Webhook endpoint for Growth Companion already exists.');
        }
    }
}
