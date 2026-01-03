<?php

namespace App\Console\Commands;

use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanEmptyBots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bots:clean-empty {--dry-run : نمایش لیست ربات‌های خالی بدون پاک کردن}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'پاک کردن رکوردهای خالی از جدول bots که اطلاعات کافی ندارند';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 حالت dry-run فعال است - هیچ رکوردی پاک نخواهد شد.');
            $this->newLine();
        }

        $this->info('🔍 در حال جستجوی رکوردهای خالی...');
        $this->newLine();

        // معیارهای رکورد خالی:
        // 1. رباتی که نه telegram_bot_token دارد و نه bale_bot_token
        // 2. رباتی که telegram_bot_token دارد اما telegram_bot_name ندارد
        // 3. رباتی که bale_bot_token دارد اما bale_bot_name ندارد
        // 4. رباتی که bot_mother_id ندارد یا 0 است (به جز ربات مادر که id=1 است)

        $emptyBots = Bot::where(function($query) {
            // معیار 1: نه telegram_bot_token دارد و نه bale_bot_token
            $query->where(function($q) {
                $q->where(function($q2) {
                    $q2->whereNull('telegram_bot_token')
                        ->orWhere('telegram_bot_token', '=', '');
                })
                ->where(function($q2) {
                    $q2->whereNull('bale_bot_token')
                        ->orWhere('bale_bot_token', '=', '');
                });
            })
            // معیار 2: telegram_bot_token دارد اما telegram_bot_name ندارد
            ->orWhere(function($q) {
                $q->whereNotNull('telegram_bot_token')
                    ->where('telegram_bot_token', '!=', '')
                    ->where(function($q2) {
                        $q2->whereNull('telegram_bot_name')
                            ->orWhere('telegram_bot_name', '=', '');
                    });
            })
            // معیار 3: bale_bot_token دارد اما bale_bot_name ندارد
            ->orWhere(function($q) {
                $q->whereNotNull('bale_bot_token')
                    ->where('bale_bot_token', '!=', '')
                    ->where(function($q2) {
                        $q2->whereNull('bale_bot_name')
                            ->orWhere('bale_bot_name', '=', '');
                    });
            })
            // معیار 4: bot_mother_id ندارد یا 0 است (به جز ربات مادر که id=1 است)
            ->orWhere(function($q) {
                $q->where(function($q2) {
                    $q2->whereNull('bot_mother_id')
                        ->orWhere('bot_mother_id', '=', 0);
                })
                ->where('id', '!=', 1); // ربات مادر را پاک نکن
            });
        })->get();

        if ($emptyBots->isEmpty()) {
            $this->info('✅ هیچ رکورد خالی یافت نشد.');
            return 0;
        }

        $this->warn("⚠️  {$emptyBots->count()} رکورد خالی یافت شد:");
        $this->newLine();

        // نمایش لیست ربات‌ها
        $headers = ['ID', 'Telegram Token', 'Telegram Name', 'Bale Token', 'Bale Name', 'Bot Mother ID'];
        $rows = [];

        foreach ($emptyBots as $bot) {
            $rows[] = [
                $bot->id,
                $bot->telegram_bot_token ? (substr($bot->telegram_bot_token, 0, 15) . '...') : 'NULL',
                $bot->telegram_bot_name ?? 'NULL',
                $bot->bale_bot_token ? (substr($bot->bale_bot_token, 0, 15) . '...') : 'NULL',
                $bot->bale_bot_name ?? 'NULL',
                $bot->bot_mother_id ?? 'NULL',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($dryRun) {
            $this->info('ℹ️  حالت dry-run فعال است - هیچ رکوردی پاک نشد.');
            $this->info('برای پاک کردن واقعی، دستور را بدون --dry-run اجرا کنید.');
            return 0;
        }

        // تایید از کاربر
        if (!$this->confirm('آیا مطمئن هستید که می‌خواهید این رکوردها را پاک کنید؟', false)) {
            $this->info('❌ عملیات لغو شد.');
            return 0;
        }

        // پاک کردن رکوردها
        $deletedCount = 0;
        $this->info('🗑️  در حال پاک کردن رکوردها...');
        $this->newLine();

        $bar = $this->output->createProgressBar($emptyBots->count());
        $bar->start();

        foreach ($emptyBots as $bot) {
            try {
                $bot->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ خطا در پاک کردن ربات #{$bot->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($deletedCount > 0) {
            $this->info("✅ {$deletedCount} رکورد با موفقیت پاک شد.");
        } else {
            $this->warn('⚠️  هیچ رکوردی پاک نشد.');
        }

        return 0;
    }
}
