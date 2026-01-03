<?php

namespace Database\Seeders;

use App\Models\AiLlm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiLlmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $aiLlmData = [
            [
                'name' => 'کلادی',
                'slug' => 'claude',
                'description' => 'هوش مصنوعی کلادی از شرکت Anthropic',
                'url' => 'https://claude.ai',
                'sort_order' => 1,
            ],
            [
                'name' => 'چت جی‌بی‌تی',
                'slug' => 'chatgpt',
                'description' => 'هوش مصنوعی ChatGPT از شرکت OpenAI',
                'url' => 'https://chat.openai.com',
                'sort_order' => 2,
            ],
            [
                'name' => 'دیب سیک',
                'slug' => 'deepseek',
                'description' => 'هوش مصنوعی دیب سیک',
                'url' => 'https://www.deepseek.com',
                'sort_order' => 3,
            ],
            [
                'name' => 'مونیکا',
                'slug' => 'monica',
                'description' => 'هوش مصنوعی مونیکا',
                'url' => 'https://monica.im',
                'sort_order' => 4,
            ],
            [
                'name' => 'جمنای',
                'slug' => 'gemini',
                'description' => 'هوش مصنوعی جمنای از شرکت گوگل',
                'url' => 'https://gemini.google.com',
                'sort_order' => 5,
            ],
            [
                'name' => 'کوبالیت',
                'slug' => 'kobalit',
                'description' => 'هوش مصنوعی کوبالیت',
                'url' => 'https://kobalit.com',
                'sort_order' => 6,
            ],
            [
                'name' => 'نوت بک ال ام',
                'slug' => 'notebooklm',
                'description' => 'هوش مصنوعی نوت بک ال ام از شرکت گوگل',
                'url' => 'https://notebooklm.google.com',
                'sort_order' => 7,
            ],
        ];

        foreach ($aiLlmData as $data) {
            AiLlm::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        $this->command->info('✅ AI/LLM Seeder completed successfully!');
    }
}
