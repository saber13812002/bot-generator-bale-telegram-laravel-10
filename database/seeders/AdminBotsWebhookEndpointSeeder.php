<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminBotsWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'admin-bots')
            ->exists();

        if ($exists) {
            $this->command->info('Admin Bots webhook endpoint already exists.');

            return;
        }

        DB::table('webhook_endpoints')->insert([
            'endpoint_id' => 'admin-bots',
            'name' => 'ربات ادمین باتس',
            'route' => 'api/webhook-admin-bots',
            'description' => 'ربات مدیریت ربات‌های شخصی — ثبت‌نام، ساخت ربات، درخواست Pro',
            'requires_bot_mother_id' => true,
            'requires_token' => true,
            'requires_language' => false,
            'supports_multiple_languages' => false,
            'is_active' => 1,
            'usage_instructions' => "1. ابتدا در وب bots.pardisania.ir/bots با OTP بله وارد شوید.\n2. سپس در این ربات دستور /link شماره_موبایل را بزنید.\n3. با /create ربات جدید بسازید (نیاز به Pro).",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Admin Bots webhook endpoint created.');
    }
}
