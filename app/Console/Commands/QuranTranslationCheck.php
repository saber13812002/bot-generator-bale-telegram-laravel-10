<?php

namespace App\Console\Commands;

use App\Services\QuranTranslationImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class QuranTranslationCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran-translations:check {--path=resources/trans : مسیر پوشه فایل‌های SQL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'بررسی فایل‌های SQL موجود و وضعیت ایمپورت ترجمه‌ها';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $path = $this->option('path');
            $fullPath = base_path($path);

            if (!File::exists($fullPath)) {
                $this->error("❌ پوشه {$fullPath} وجود ندارد.");
                return 1;
            }

            if (!File::isDirectory($fullPath)) {
                $this->error("❌ {$fullPath} یک پوشه نیست.");
                return 1;
            }

            $this->info("📁 بررسی پوشه: {$fullPath}\n");

            // دریافت همه فایل‌های .sql
            $sqlFiles = File::glob($fullPath . '/*.sql');

            if (empty($sqlFiles)) {
                $this->warn('⚠️ هیچ فایل SQL یافت نشد.');
                return 0;
            }

            $this->info("✅ تعداد فایل‌های یافت شده: " . count($sqlFiles) . "\n");

            $service = new QuranTranslationImportService();
            $tableData = [];
            $importedCount = 0;
            $notImportedCount = 0;

            foreach ($sqlFiles as $filePath) {
                $fileName = basename($filePath);
                
                try {
                    // استخراج metadata از نام فایل
                    $content = File::get($filePath);
                    $metadata = $service->extractMetadata($content, $fileName);

                    $language = $metadata['language'] ?? 'نامشخص';
                    $translator = $metadata['translator_name'] ?? 'نامشخص';
                    $fullName = $metadata['translate_full_name'] ?? 'نامشخص';

                    // بررسی وجود در دیتابیس
                    $exists = $service->checkIfExists($language, $translator);
                    $ayatCount = 0;
                    $status = '❌ ایمپورت نشده';

                    if ($exists) {
                        $ayatCount = $service->getExistingAyatCount($language, $translator);
                        $status = $ayatCount == 6236 
                            ? "✅ کامل ({$ayatCount} آیات)" 
                            : "⚠️ ناقص ({$ayatCount} آیات)";
                        $importedCount++;
                    } else {
                        $notImportedCount++;
                    }

                    $tableData[] = [
                        $fileName,
                        $language,
                        $translator,
                        $fullName,
                        $status,
                    ];

                } catch (\Exception $e) {
                    $this->warn("⚠️ خطا در پردازش {$fileName}: " . $e->getMessage());
                    $tableData[] = [
                        $fileName,
                        'خطا',
                        'خطا',
                        'خطا',
                        '❌ خطا در پردازش',
                    ];
                }
            }

            // نمایش جدول
            $this->table(
                ['فایل', 'زبان', 'مترجم', 'نام کامل', 'وضعیت'],
                $tableData
            );

            // خلاصه
            $this->info("\n📊 خلاصه:");
            $this->line("  ✅ ایمپورت شده: {$importedCount}");
            $this->line("  ❌ ایمپورت نشده: {$notImportedCount}");
            $this->line("  📁 کل فایل‌ها: " . count($sqlFiles));

            // پیشنهاد
            if ($notImportedCount > 0) {
                $this->info("\n💡 برای ایمپورت فایل‌های جدید:");
                $this->line("   php artisan quran-translations:import-all");
                $this->line("   یا برای ایمپورت یک فایل خاص:");
                $this->line("   php artisan quran-translation:import resources/trans/{filename}.sql");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ خطا: ' . $e->getMessage());
            return 1;
        }
    }
}
