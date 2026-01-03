<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\BotLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UpdateBotLogsBotId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot-logs:update-bot-id 
                            {--dry-run : نمایش لیست لاگ‌ها بدون به‌روزرسانی}
                            {--batch-size=1000 : تعداد رکوردهای پردازش شده در هر batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'به‌روزرسانی bot_id و bot_mother_id در جدول bot_logs برای تمام لاگ‌های موجود';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($dryRun) {
            $this->info('🔍 حالت dry-run فعال است - هیچ رکوردی به‌روزرسانی نخواهد شد.');
            $this->newLine();
        }

        $this->info('🔍 در حال دریافت لاگ‌های موجود...');
        $this->newLine();

        // دریافت تمام لاگ‌هایی که bot_id یا bot_mother_id ندارند یا نیاز به به‌روزرسانی دارند
        $totalLogs = BotLog::count();
        $this->info("📊 تعداد کل لاگ‌ها: {$totalLogs}");
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        // پردازش به صورت batch برای عملکرد بهتر
        $bar = $this->output->createProgressBar($totalLogs);
        $bar->start();

        BotLog::chunk($batchSize, function ($logs) use (&$updated, &$skipped, &$errors, $dryRun, $bar) {
            foreach ($logs as $log) {
                try {
                    $needsUpdate = false;
                    $newBotId = $log->bot_id;
                    $newBotMotherId = $log->bot_mother_id ?? 0;

                    // اگر bot_id نداریم یا bot_id = 1 (پیش‌فرض) است، سعی می‌کنیم پیدا کنیم
                    if (!$log->bot_id || $log->bot_id == 1) {
                        $botId = $this->findBotIdForLog($log);
                        if ($botId && $botId != $log->bot_id) {
                            $newBotId = $botId;
                            $needsUpdate = true;
                        }
                    }

                    // اگر bot_mother_id نداریم یا 0 است، سعی می‌کنیم از bot_id پیدا کنیم
                    if (!$log->bot_mother_id || $log->bot_mother_id == 0) {
                        $botMotherId = $this->findBotMotherIdForLog($log);
                        if ($botMotherId && $botMotherId != $log->bot_mother_id) {
                            $newBotMotherId = $botMotherId;
                            $needsUpdate = true;
                        }
                    }

                    if ($needsUpdate && !$dryRun) {
                        $log->bot_id = $newBotId;
                        $log->bot_mother_id = $newBotMotherId;
                        $log->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }

                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Error updating bot log', [
                        'log_id' => $log->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }
        });

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
                ['📊 کل', $totalLogs],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('ℹ️  حالت dry-run فعال است - هیچ رکوردی به‌روزرسانی نشد.');
            $this->info('برای به‌روزرسانی واقعی، دستور را بدون --dry-run اجرا کنید.');
        }

        return 0;
    }

    /**
     * پیدا کردن bot_id برای یک لاگ
     */
    private function findBotIdForLog(BotLog $log): ?int
    {
        try {
            // روش 1: استفاده از bot_mother_id، type، language و endpoint_id
            if ($log->bot_mother_id && $log->type && $log->language && $log->webhook_endpoint_uri) {
                // تبدیل endpoint URI به endpoint_id
                // webhook_endpoint_uri ممکن است "quran-word" یا "webhook-quran-word" باشد
                $endpointId = $log->webhook_endpoint_uri;
                if (!str_starts_with($endpointId, 'webhook-')) {
                    $endpointId = 'webhook-' . $endpointId;
                }

                $query = Bot::where('bot_mother_id', $log->bot_mother_id)
                    ->where('type', $log->type)
                    ->where('language_code', $log->language)
                    ->where('endpoint_id', $endpointId);

                $bot = $query->first();

                if ($bot) {
                    return $bot->id;
                }
            }

            // روش 2: استفاده از bot_mother_id، type و language (بدون endpoint)
            if ($log->bot_mother_id && $log->type && $log->language) {
                $query = Bot::where('bot_mother_id', $log->bot_mother_id)
                    ->where('type', $log->type)
                    ->where('language_code', $log->language);

                $bots = $query->get();

                if ($bots->count() == 1) {
                    return $bots->first()->id;
                }
            }

            // روش 3: استفاده از bot_mother_id و type (اگر فقط یک ربات با این مشخصات وجود دارد)
            if ($log->bot_mother_id && $log->type) {
                $query = Bot::where('bot_mother_id', $log->bot_mother_id);
                
                if ($log->type == 'telegram') {
                    $query->whereNotNull('telegram_bot_token')->where('telegram_bot_token', '!=', '');
                } elseif ($log->type == 'bale') {
                    $query->whereNotNull('bale_bot_token')->where('bale_bot_token', '!=', '');
                }

                $bots = $query->get();

                if ($bots->count() == 1) {
                    return $bots->first()->id;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error finding bot_id for log', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * پیدا کردن bot_mother_id برای یک لاگ
     */
    private function findBotMotherIdForLog(BotLog $log): ?int
    {
        try {
            // اگر bot_id داریم، از آن استفاده می‌کنیم
            if ($log->bot_id) {
                $bot = Bot::find($log->bot_id);
                if ($bot && $bot->bot_mother_id) {
                    return $bot->bot_mother_id;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error finding bot_mother_id for log', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
