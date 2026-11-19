<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentSubmissionWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'content-submission')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'content-submission',
                'name' => 'محتوای متنی / عکس / فیلم',
                'route' => 'api/webhook-content-submission',
                'description' => 'ربات دریافت محتوا (متن/عکس/فیلم)، تایید در گروه با ریپلای «۱»، انتشار در کانال',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => false,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for Content Submission Bot created successfully.');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for Content Submission Bot already exists.');
        }
    }
}
