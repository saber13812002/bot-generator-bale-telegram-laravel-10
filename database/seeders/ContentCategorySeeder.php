<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use Illuminate\Database\Seeder;

class ContentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $botId = (int) (env('CONTENT_SEED_BOT_ID') ?: env('BOOK_LIBRARY_SEED_BOT_ID') ?: 0);
        if ($botId <= 0) {
            $this->command?->warn('CONTENT_SEED_BOT_ID not set — skipping.');
            return;
        }

        $categories = [
            1 => ['روانشناسی', 'کسب و کار', 'موفقیت', 'تاریخ', 'اقتصاد'],
            2 => ['فلسفه', 'معرفت نفس', 'رمان', 'دین', 'آموزش'],
            3 => ['زندگینامه', 'بازاریابی', 'مدیریت', 'تربیت فرزند', 'خانواده', 'سبک زندگی'],
        ];

        foreach ($categories as $page => $titles) {
            foreach ($titles as $order => $title) {
                ContentCategory::firstOrCreate(
                    ['bot_id' => $botId, 'title' => $title],
                    ['page' => $page, 'sort_order' => $order + 1, 'is_active' => true]
                );
            }
        }

        $this->command?->info("Content categories seeded for bot_id={$botId}");
    }
}
