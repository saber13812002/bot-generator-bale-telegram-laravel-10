<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'نمایش لیست کامل ترجمه‌های موجود در دیتابیس';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // بررسی وجود جدول
            if (!DB::getSchemaBuilder()->hasTable('translations')) {
                $this->error('❌ جدول translations در دیتابیس وجود ندارد.');
                return 1;
            }

            // دریافت تمام ترجمه‌ها
            $translations = DB::table('translations')
                ->orderBy('language')
                ->orderBy('name')
                ->get();

            if ($translations->isEmpty()) {
                $this->info('📭 هیچ ترجمه‌ای در دیتابیس وجود ندارد.');
                return 0;
            }

            $this->info("📚 لیست ترجمه‌های موجود در دیتابیس\n");
            $this->info("تعداد کل: {$translations->count()} ترجمه\n");

            // گروه‌بندی بر اساس زبان
            $groupedByLanguage = $translations->groupBy('language');

            $tableData = [];
            foreach ($groupedByLanguage as $language => $langTranslations) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🌍 زبان: {$language} ({$langTranslations->count()} ترجمه)");
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

                foreach ($langTranslations as $index => $translation) {
                    $number = $index + 1;
                    $this->line("  {$number}. {$translation->name}");
                    $this->line("     مترجم: {$translation->translator}");
                    $this->line("     فایل: {$translation->filename}");
                    $this->line("");
                }
            }

            // نمایش خلاصه
            $this->info("\n📊 خلاصه:");
            $this->table(
                ['زبان', 'تعداد ترجمه'],
                $groupedByLanguage->map(function ($translations, $language) {
                    return [$language, $translations->count()];
                })->values()->toArray()
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ خطا در دریافت لیست ترجمه‌ها: ' . $e->getMessage());
            return 1;
        }
    }
}
