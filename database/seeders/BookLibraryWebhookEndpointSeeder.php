<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookLibraryWebhookEndpointSeeder extends Seeder
{
    public function run(): void
    {
        $endpoints = [
            [
                'endpoint_id' => 'book-library',
                'name' => 'Smart Book Library',
                'route' => 'api/webhook-book-library',
                'description' => 'AI Book Coach - discover books, audio summaries, plan upgrades',
            ],
            [
                'endpoint_id' => 'book-library-reader',
                'name' => 'Book Library Reader',
                'route' => 'api/webhook-book-library-reader',
                'description' => 'Dedicated reader bot for book audio/PDF delivery',
            ],
        ];

        foreach ($endpoints as $endpoint) {
            $exists = DB::table('webhook_endpoints')
                ->where('endpoint_id', $endpoint['endpoint_id'])
                ->exists();

            if (!$exists) {
                DB::table('webhook_endpoints')->insert(array_merge($endpoint, [
                    'requires_bot_mother_id' => true,
                    'requires_token' => true,
                    'requires_language' => false,
                    'supports_multiple_languages' => true,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $this->command?->info("✅ Endpoint {$endpoint['endpoint_id']} created.");
            }
        }

        $this->linkRelatedEndpoints();
    }

    private function linkRelatedEndpoints(): void
    {
        $main = DB::table('webhook_endpoints')->where('endpoint_id', 'book-library')->first();
        $reader = DB::table('webhook_endpoints')->where('endpoint_id', 'book-library-reader')->first();

        if (!$main || !$reader) {
            return;
        }

        $exists = DB::table('webhook_endpoint_related_bots')
            ->where('webhook_endpoint_id', $main->id)
            ->where('related_webhook_endpoint_id', $reader->id)
            ->exists();

        if (!$exists) {
            DB::table('webhook_endpoint_related_bots')->insert([
                'webhook_endpoint_id' => $main->id,
                'related_webhook_endpoint_id' => $reader->id,
                'order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
