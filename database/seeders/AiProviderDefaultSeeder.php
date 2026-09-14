<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiProviderDefaultSeeder extends Seeder
{
    public function run(): void
    {
        AiProvider::firstOrCreate(
            ['base_url' => config('ai.default_base_url')],
            [
                'name'            => 'ISMC AI Server',
                'base_url'        => config('ai.default_base_url', 'https://ai.ismc.ir/api'),
                'api_key'         => config('ai.default_api_key', ''),
                'model_name'      => config('ai.default_model', 'qwen38'),
                'default_prompt'  => config('ai.default_prompt', 'سلام! جواب سلام بده و در ۴ کاراکتر'),
                'provider_type'   => 'openai_compatible',
                'is_active'       => true,
                'notify_platform' => config('ai.notify_type', 'bale'),
            ]
        );

        $this->command->info('✅ AiProvider پیش‌فرض (ISMC) ساخته شد.');
    }
}
