<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListTranslationSeeders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:list-seeders {--path=database/seeders : مسیر پوشه seeder ها}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'نمایش لیست پوشه‌ها و فایل‌های seeder ترجمه‌ها که آماده برای ایمپورت هستند';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $seederPath = $this->option('path');
            $fullPath = base_path($seederPath);

            if (!File::exists($fullPath)) {
                $this->error("❌ پوشه {$fullPath} وجود ندارد.");
                return 1;
            }

            $this->info("📁 بررسی پوشه: {$fullPath}\n");

            // لیست فایل‌های seeder مرتبط با ترجمه
            $translationSeeders = [
                'TranslationsTableSeeder.php',
                'QuranTranslationsTableSeeder.php',
                'RssPostItemTranslationsTableSeeder.php',
                'TranslationPublishSeeder.php',
            ];

            $foundSeeders = [];
            $allSeeders = File::files($fullPath);

            foreach ($allSeeders as $file) {
                $fileName = $file->getFilename();
                
                // بررسی فایل‌های مرتبط با ترجمه
                if (in_array($fileName, $translationSeeders) || 
                    stripos($fileName, 'translation') !== false ||
                    stripos($fileName, 'translator') !== false) {
                    $foundSeeders[] = [
                        'file' => $fileName,
                        'path' => $file->getPathname(),
                        'size' => $this->formatBytes($file->getSize()),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }

            if (empty($foundSeeders)) {
                $this->warn('⚠️ هیچ فایل seeder مرتبط با ترجمه یافت نشد.');
                return 0;
            }

            $this->info("✅ تعداد فایل‌های یافت شده: " . count($foundSeeders) . "\n");

            // نمایش لیست فایل‌ها
            $this->table(
                ['شماره', 'نام فایل', 'اندازه', 'تاریخ تغییر'],
                array_map(function ($seeder, $index) {
                    return [
                        $index + 1,
                        $seeder['file'],
                        $seeder['size'],
                        $seeder['modified'],
                    ];
                }, $foundSeeders, array_keys($foundSeeders))
            );

            // نمایش جزئیات هر فایل
            $this->info("\n📋 جزئیات فایل‌ها:\n");
            foreach ($foundSeeders as $index => $seeder) {
                $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->line(($index + 1) . ". {$seeder['file']}");
                $this->line("   📁 مسیر: {$seeder['path']}");
                $this->line("   📦 اندازه: {$seeder['size']}");
                $this->line("   📅 تاریخ تغییر: {$seeder['modified']}");
                
                // بررسی محتوای فایل برای تعداد ترجمه‌ها
                try {
                    $content = File::get($seeder['path']);
                    $translationCount = $this->countTranslationsInSeeder($content, $seeder['file']);
                    if ($translationCount > 0) {
                        $this->line("   📊 تعداد ترجمه‌ها: {$translationCount}");
                    }
                } catch (\Exception $e) {
                    $this->warn("   ⚠️ خطا در خواندن فایل: " . $e->getMessage());
                }
                
                $this->line("");
            }

            // پیشنهاد برای ایمپورت
            $this->info("💡 برای ایمپورت ترجمه‌ها از seeder ها، می‌توانید از دستور زیر استفاده کنید:");
            $this->line("   php artisan db:seed --class=TranslationsTableSeeder");
            $this->line("   php artisan db:seed --class=QuranTranslationsTableSeeder");
            $this->line("   php artisan db:seed --class=RssPostItemTranslationsTableSeeder");

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ خطا: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * شمارش تعداد ترجمه‌ها در فایل seeder
     * 
     * @param string $content محتوای فایل
     * @param string $fileName نام فایل
     * @return int
     */
    private function countTranslationsInSeeder(string $content, string $fileName): int
    {
        // برای TranslationsTableSeeder
        if (strpos($fileName, 'TranslationsTableSeeder') !== false) {
            // شمارش آرایه‌های insert
            preg_match_all('/array\s*\(/', $content, $matches);
            return count($matches[0]) ?? 0;
        }

        // برای سایر seeder ها
        if (stripos($content, 'insert') !== false || stripos($content, 'create') !== false) {
            // تلاش برای شمارش رکوردها
            preg_match_all('/\d+\s*=>\s*array\s*\(/', $content, $matches);
            return count($matches[0]) ?? 0;
        }

        return 0;
    }

    /**
     * فرمت کردن اندازه فایل
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
