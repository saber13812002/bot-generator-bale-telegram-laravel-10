<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MawkibFinderWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('webhook_endpoints')
            ->where('endpoint_id', 'mawkib-finder')
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoints')->insert([
                'endpoint_id' => 'mawkib-finder',
                'name' => 'موکب یاب',
                'route' => 'api/webhook-mawkib-finder',
                'description' => 'ربات یافتن موکب — احراز هویت، انتخاب استان و تاریخ ورود',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
                'supports_multiple_languages' => true,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('✅ Webhook endpoint for Mawkib Finder Bot created successfully!');
        } else {
            $this->command->info('ℹ️  Webhook endpoint for Mawkib Finder Bot already exists.');
        }
    }
}
