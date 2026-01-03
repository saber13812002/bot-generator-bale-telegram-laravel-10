<?php

namespace App\Console\Commands;

use App\Models\BotUsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetDefaultQuranTranslation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran-translation:set-default 
                            {--language= : زبان ترجمه (مثال: fa, en, ar)}
                            {--translator= : نام مترجم (مثال: ansarian, yusufali)}
                            {--bot-mother-id= : شناسه ربات مادر (اختیاری)}
                            {--force : بازنویسی setting های موجود}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تغییر ترجمه پیش‌فرض کاربران ربات قرآن';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $language = $this->option('language');
        $translator = $this->option('translator');
        $botMotherId = $this->option('bot-mother-id');
        $force = $this->option('force');

        // بررسی پارامترهای الزامی
        if (!$language || !$translator) {
            $this->error('❌ لطفاً زبان و مترجم را مشخص کنید.');
            $this->info('مثال: php artisan quran-translation:set-default --language=fa --translator=ansarian');
            return 1;
        }

        $this->info("🔄 در حال تغییر ترجمه پیش‌فرض...");
        $this->info("   زبان: {$language}");
        $this->info("   مترجم: {$translator}");
        if ($botMotherId) {
            $this->info("   ربات مادر: {$botMotherId}");
        }
        if ($force) {
            $this->info("   ⚠️  حالت force فعال است - setting های موجود بازنویسی می‌شوند");
        }

        // ساخت query
        $query = BotUsers::query()
            ->where('origin', '!=', 'gap') // فقط telegram و bale
            ->whereNotNull('settings');

        // فیلتر بر اساس bot_mother_id
        if ($botMotherId) {
            $query->where('bot_mother_id', $botMotherId);
        }

        // فیلتر بر اساس setting های موجود (اگر force نباشد)
        if (!$force) {
            $query->where(function ($q) {
                $q->whereRaw("JSON_EXTRACT(settings, '$.quran_translation_language') IS NULL")
                  ->orWhereRaw("JSON_EXTRACT(settings, '$.quran_translation_translator') IS NULL");
            });
        }

        $users = $query->get();
        $totalUsers = $users->count();

        if ($totalUsers == 0) {
            $this->warn('⚠️  هیچ کاربری یافت نشد.');
            return 0;
        }

        $this->info("📊 تعداد کاربران: {$totalUsers}");
        $this->newLine();

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                $settings = $user->settings ?? [];
                
                // اگر force نباشد و setting وجود داشته باشد، skip می‌کنیم
                if (!$force && isset($settings['quran_translation_language']) && isset($settings['quran_translation_translator'])) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // به‌روزرسانی setting
                $settings['quran_translation_language'] = $language;
                $settings['quran_translation_translator'] = $translator;

                // حفظ translation_id برای سازگاری
                if (!isset($settings['translation_id'])) {
                    $settings['translation_id'] = 2; // پیش‌فرض
                }

                $user->settings = $settings;
                $user->save();

                $updated++;
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error updating user translation', [
                    'user_id' => $user->id,
                    'chat_id' => $user->chat_id,
                    'error' => $e->getMessage()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // نمایش نتایج
        $this->info("✅ به‌روزرسانی انجام شد!");
        $this->table(
            ['وضعیت', 'تعداد'],
            [
                ['✅ به‌روزرسانی شده', $updated],
                ['⏭️  رد شده', $skipped],
                ['❌ خطا', $errors],
                ['📊 کل', $totalUsers],
            ]
        );

        if ($errors > 0) {
            $this->warn("⚠️  {$errors} خطا رخ داد. لطفاً لاگ‌ها را بررسی کنید.");
        }

        return 0;
    }
}
