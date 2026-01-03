<?php

namespace App\Console\Commands;

use App\Services\QuranTranslationImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportQuranTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran-translations:import-all {--path=resources/trans : مسیر پوشه فایل‌های SQL} {--force : ایمپورت همه فایل‌ها حتی اگر قبلاً ایمپورت شده‌اند} {--dry-run : فقط نمایش فایل‌هایی که ایمپورت می‌شوند}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ایمپورت همه فایل‌های SQL ترجمه قرآن که قبلاً ایمپورت نشده‌اند';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

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

        $service = new QuranTranslationImportService();
        $filesToImport = [];
        $filesSkipped = [];

        // بررسی فایل‌ها
        $this->info("🔍 در حال بررسی فایل‌ها...\n");

        foreach ($sqlFiles as $filePath) {
            $fileName = basename($filePath);
            
            try {
                $content = File::get($filePath);
                $metadata = $service->extractMetadata($content, $fileName);

                $language = $metadata['language'] ?? null;
                $translator = $metadata['translator_name'] ?? null;

                if (!$language || !$translator) {
                    $this->warn("⚠️ نتوانستیم metadata را از {$fileName} استخراج کنیم.");
                    $filesSkipped[] = ['file' => $fileName, 'reason' => 'metadata یافت نشد'];
                    continue;
                }

                $exists = $service->checkIfExists($language, $translator);

                if ($exists && !$force) {
                    $ayatCount = $service->getExistingAyatCount($language, $translator);
                    $filesSkipped[] = [
                        'file' => $fileName,
                        'reason' => "قبلاً ایمپورت شده ({$ayatCount} آیات)",
                    ];
                } else {
                    $filesToImport[] = [
                        'path' => $filePath,
                        'fileName' => $fileName,
                        'language' => $language,
                        'translator' => $translator,
                        'metadata' => $metadata,
                    ];
                }

            } catch (\Exception $e) {
                $this->warn("⚠️ خطا در پردازش {$fileName}: " . $e->getMessage());
                $filesSkipped[] = ['file' => $fileName, 'reason' => 'خطا: ' . $e->getMessage()];
            }
        }

        // نمایش فایل‌های رد شده
        if (!empty($filesSkipped)) {
            $this->info("⏭️ فایل‌های رد شده: " . count($filesSkipped));
            foreach ($filesSkipped as $skipped) {
                $this->line("   • {$skipped['file']}: {$skipped['reason']}");
            }
            $this->newLine();
        }

        // نمایش فایل‌های قابل ایمپورت
        if (empty($filesToImport)) {
            $this->info("✅ همه فایل‌ها قبلاً ایمپورت شده‌اند.");
            if (!$force) {
                $this->line("   برای ایمپورت مجدد از --force استفاده کنید.");
            }
            return 0;
        }

        $this->info("📦 فایل‌های قابل ایمپورت: " . count($filesToImport));
        foreach ($filesToImport as $file) {
            $this->line("   • {$file['fileName']} ({$file['language']}.{$file['translator']})");
        }
        $this->newLine();

        if ($dryRun) {
            $this->info("🔍 حالت dry-run: هیچ فایلی ایمپورت نمی‌شود.");
            return 0;
        }

        // تایید از کاربر
        if (!$this->confirm("آیا می‌خواهید این فایل‌ها را ایمپورت کنید؟", true)) {
            $this->info("❌ عملیات لغو شد.");
            return 0;
        }

        // ایمپورت فایل‌ها
        $this->newLine();
        $totalSuccess = 0;
        $totalFailed = 0;
        $results = [];

        foreach ($filesToImport as $index => $file) {
            $fileNum = $index + 1;
            $this->info("[{$fileNum}/" . count($filesToImport) . "] در حال پردازش: {$file['fileName']}");

            try {
                // پارس فایل
                $parsed = $service->parseSqlFile($file['path']);
                $data = $parsed['data'];

                // تبدیل ساختار
                $transformedData = $service->transformData($data, $file['metadata']);

                // ایمپورت
                $result = $service->import($transformedData, $force);

                if ($result['success']) {
                    $this->info("   ✅ موفق: {$result['inserted']} آیات");
                    $totalSuccess++;
                    $results[] = [
                        'file' => $file['fileName'],
                        'status' => 'success',
                        'inserted' => $result['inserted'],
                    ];
                } else {
                    $this->error("   ❌ خطا: {$result['message']}");
                    $totalFailed++;
                    $results[] = [
                        'file' => $file['fileName'],
                        'status' => 'failed',
                        'error' => $result['message'],
                    ];
                }

            } catch (\Exception $e) {
                $this->error("   ❌ خطا: " . $e->getMessage());
                $totalFailed++;
                $results[] = [
                    'file' => $file['fileName'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }

            $this->newLine();
        }

        // نمایش خلاصه
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 خلاصه:");
        $this->info("   ✅ موفق: {$totalSuccess}");
        $this->info("   ❌ ناموفق: {$totalFailed}");
        $this->info("   📁 کل: " . count($filesToImport));
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return $totalFailed > 0 ? 1 : 0;
    }
}
