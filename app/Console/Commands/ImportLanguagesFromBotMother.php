<?php

namespace App\Console\Commands;

use App\Models\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLanguagesFromBotMother extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'languages:import-from-bot-mother 
                            {--force : بازنویسی رکوردهای موجود}
                            {--dry-run : نمایش لیست زبان ها بدون ذخیره کردن}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import کردن زبان های موجود در BotMotherController به جدول languages';

    /**
     * لیست زبان های پشتیبانی شده (از BotMotherController)
     */
    private function getSupportedLanguages(): array
    {
        return [
            'fa' => '🇮🇷 فارسی',
            'en' => '🇬🇧 English',
            'ar-IQ' => '🇮🇶 العربية (عراق)',
            'az' => '🇦🇿 Azərbaycan',
            'bs' => '🇧🇦 Bosanski',
            'de-DE' => '🇩🇪 Deutsch',
            'es' => '🇪🇸 Español',
            'fr' => '🇫🇷 Français',
            'he' => '🇮🇱 עברית',
            'it' => '🇮🇹 Italiano',
            'id' => '🇮🇩 Bahasa Indonesia',
            'sw' => '🇰🇪 Kiswahili',
            'pt-BR' => '🇧🇷 Português (Brasil)',
            'pt-PT' => '🇵🇹 Português (Portugal)',
            'ru' => '🇷🇺 Русский',
            'tr' => '🇹🇷 Türkçe',
            'ur' => '🇵🇰 اردو',
            'zh-CN' => '🇨🇳 中文',
        ];
    }

    /**
     * استخراج نام زبان از display_name
     */
    private function extractLanguageName(string $displayName): array
    {
        // حذف ایموجی و فضای خالی
        $name = preg_replace('/^[\x{1F1E6}-\x{1F1FF}\x{1F300}-\x{1F9FF}]+\s*/u', '', $displayName);
        
        return [
            'name' => trim($name),
            'native_name' => trim($name),
        ];
    }

    /**
     * استخراج ایموجی از display_name
     */
    private function extractFlagEmoji(string $displayName): ?string
    {
        if (preg_match('/^([\x{1F1E6}-\x{1F1FF}\x{1F300}-\x{1F9FF}]+)/u', $displayName, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 حالت dry-run فعال است - هیچ رکوردی ذخیره نخواهد شد.');
            $this->newLine();
        }

        $this->info('📖 در حال آماده‌سازی زبان های پشتیبانی شده...');
        $this->newLine();

        // دریافت زبان های پشتیبانی شده
        $languages = $this->getSupportedLanguages();
        $totalLanguages = count($languages);

        if ($totalLanguages == 0) {
            $this->warn('⚠️  هیچ زبانی یافت نشد.');
            return 0;
        }

        $this->info("📊 تعداد زبان های یافت شده: {$totalLanguages}");
        $this->newLine();

        // نمایش لیست زبان ها
        $headers = ['Code', 'Display Name', 'Name', 'Flag Emoji'];
        $rows = [];

        foreach ($languages as $code => $displayName) {
            $nameParts = $this->extractLanguageName($displayName);
            $flagEmoji = $this->extractFlagEmoji($displayName);
            
            $rows[] = [
                $code,
                $displayName,
                $nameParts['name'],
                $flagEmoji ?? '-',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($dryRun) {
            $this->info('ℹ️  حالت dry-run فعال است - هیچ رکوردی ذخیره نشد.');
            $this->info('برای ذخیره کردن واقعی، دستور را بدون --dry-run اجرا کنید.');
            return 0;
        }

        // شروع import
        $this->info('💾 در حال ذخیره زبان ها در جدول languages...');
        $this->newLine();

        $bar = $this->output->createProgressBar($totalLanguages);
        $bar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            foreach ($languages as $code => $displayName) {
                try {
                    $nameParts = $this->extractLanguageName($displayName);
                    $flagEmoji = $this->extractFlagEmoji($displayName);

                    // بررسی وجود رکورد با همین code
                    $existingLanguage = Language::where('code', $code)->first();

                    if ($existingLanguage) {
                        if (!$force) {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }

                        // به‌روزرسانی رکورد موجود
                        $existingLanguage->update([
                            'name' => $nameParts['name'],
                            'native_name' => $nameParts['native_name'],
                            'flag_emoji' => $flagEmoji,
                            'display_name' => $displayName,
                        ]);

                        $updated++;
                    } else {
                        // ایجاد رکورد جدید
                        Language::create([
                            'code' => $code,
                            'name' => $nameParts['name'],
                            'native_name' => $nameParts['native_name'],
                            'flag_emoji' => $flagEmoji,
                            'display_name' => $displayName,
                            'is_active' => true,
                        ]);

                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("❌ خطا در import کردن زبان '{$code}': {$e->getMessage()}");
                }

                $bar->advance();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine(2);
            $this->error("❌ خطای کلی در import: {$e->getMessage()}");
            return 1;
        }

        $bar->finish();
        $this->newLine(2);

        // نمایش نتایج
        $this->info("✅ Import انجام شد!");
        $this->table(
            ['وضعیت', 'تعداد'],
            [
                ['✅ ایجاد شده', $created],
                ['🔄 به‌روزرسانی شده', $updated],
                ['⏭️  رد شده', $skipped],
                ['❌ خطا', $errors],
                ['📊 کل', $totalLanguages],
            ]
        );

        if ($errors > 0) {
            $this->warn("⚠️  {$errors} خطا رخ داد.");
        }

        return 0;
    }
}
