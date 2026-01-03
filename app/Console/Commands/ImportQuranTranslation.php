<?php

namespace App\Console\Commands;

use App\Services\QuranTranslationImportService;
use Illuminate\Console\Command;

class ImportQuranTranslation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran-translation:import {file : مسیر فایل SQL} {--force : بازنویسی ترجمه موجود}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ایمپورت یک فایل SQL ترجمه قرآن به جدول quran_translations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $force = $this->option('force');

        // بررسی وجود فایل
        if (!file_exists($filePath)) {
            // اگر مسیر نسبی است، سعی می‌کنیم از base_path استفاده کنیم
            $fullPath = base_path($filePath);
            if (!file_exists($fullPath)) {
                $this->error("❌ فایل {$filePath} وجود ندارد.");
                return 1;
            }
            $filePath = $fullPath;
        }

        $this->info("📄 در حال پردازش فایل: {$filePath}\n");

        try {
            $service = new QuranTranslationImportService();
            $startTime = microtime(true);

            // پارس فایل SQL
            $this->info("⏳ در حال پارس فایل SQL...");
            $parsed = $service->parseSqlFile($filePath);

            $metadata = $parsed['metadata'];
            $data = $parsed['data'];

            $this->info("✅ فایل با موفقیت پارس شد.");
            $this->line("   زبان: {$metadata['language']}");
            $this->line("   مترجم: {$metadata['translator_name']}");
            $this->line("   نام کامل: {$metadata['translate_full_name']}");
            $this->line("   تعداد آیات: " . count($data) . "\n");

            // بررسی وجود ترجمه
            $exists = $service->checkIfExists($metadata['language'], $metadata['translator_name']);
            if ($exists && !$force) {
                $ayatCount = $service->getExistingAyatCount($metadata['language'], $metadata['translator_name']);
                $this->warn("⚠️ این ترجمه قبلاً ایمپورت شده است ({$ayatCount} آیات).");
                $this->line("   برای بازنویسی از --force استفاده کنید.");
                return 0;
            }

            if ($exists && $force) {
                $this->warn("⚠️ ترجمه موجود حذف می‌شود و دوباره ایمپورت می‌شود...\n");
            }

            // تبدیل ساختار داده
            $this->info("⏳ در حال تبدیل ساختار داده...");
            $transformedData = $service->transformData($data, $metadata);
            $this->info("✅ تبدیل ساختار انجام شد (" . count($transformedData) . " رکورد).\n");

            // ایمپورت
            $this->info("⏳ در حال ایمپورت به دیتابیس...");
            $bar = $this->output->createProgressBar(count($transformedData));
            $bar->start();

            $result = $service->import($transformedData, $force);

            $bar->finish();
            $this->newLine(2);

            // نمایش نتیجه
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            if ($result['success']) {
                $this->info("✅ ایمپورت با موفقیت انجام شد!");
                $this->line("   تعداد آیات ایمپورت شده: {$result['inserted']}");
                $this->line("   زمان: {$duration} ثانیه");
                
                if (!empty($result['errors'])) {
                    $this->warn("   ⚠️ تعداد خطاها: " . count($result['errors']));
                }
            } else {
                $this->error("❌ خطا در ایمپورت: {$result['message']}");
                if (!empty($result['errors'])) {
                    $this->error("   خطاها:");
                    foreach ($result['errors'] as $error) {
                        $this->error("     - " . ($error['error'] ?? json_encode($error)));
                    }
                }
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ خطا: " . $e->getMessage());
            $this->error("   Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}
