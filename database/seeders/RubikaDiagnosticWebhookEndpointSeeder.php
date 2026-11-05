<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint ربات تشخیصی روبیکا (BM-RUBIKA-DIAGNOSTIC-BOT).
 *
 * نوع ربات جدید BotMother — مستقل از GetChatID (get-chat-id)؛
 * بدون تغییر در هیچ endpoint موجود.
 *
 * ⚠️ اجرای مستقیم (php artisan db:seed --class=...) اختیاری است:
 * این endpoint هم‌زمان در WebhookEndpointDefaultImporter ثبت شده
 * و با اولین اجرای ImportWebhookEndpointsDefault (یا populate خودکار
 * WebhookEndpointCatalogService) ایجاد می‌شود.
 */
class RubikaDiagnosticWebhookEndpointSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // بررسی اینکه endpoint قبلاً وجود دارد یا نه
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'rubika-diagnostic')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'rubika-diagnostic',
                'name' => 'ربات تشخیصی روبیکا',
                'route' => 'api/webhook-rubika-diagnostic',
                'description' => 'ربات تشخیصی روبیکا — نمایش Chat ID، Sender ID، Message ID، نوع چت، پیام فورواردشده و رویدادهای BotJoined/BotRemoved/BotPermissionsChanged (مخصوص عیب‌یابی و تست API روبیکا)',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => false,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for Rubika Diagnostic Bot created successfully!');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for Rubika Diagnostic Bot already exists.');
        }
    }
}
