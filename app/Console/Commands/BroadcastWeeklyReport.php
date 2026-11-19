<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ContentUserProgress;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class BroadcastWeeklyReport extends Command
{
    protected $signature = 'user:broadcast-weekly-report 
                            {--bot-id= : شناسه ربات (اجباری)} 
                            {--days=7 : تعداد روزهای گذشته برای بررسی فعالیت}';
                            
    protected $description = 'ارسال کارنامه فعالیت برای تمام کاربرانی که در روزهای اخیر فعال بوده‌اند';

    public function handle(): int
    {
        $botId = $this->option('bot-id');
        if (!$botId) {
            $this->error('❌ لطفاً شناسه ربات را با گزینه --bot-id مشخص کنید. مثال: --bot-id=55');
            return 1;
        }

        $days = (int) $this->option('days');
        $dateThreshold = Carbon::now()->subDays($days);

        $this->info("🔍 در حال جستجوی کاربرانی که در {$days} روز گذشته در ربات {$botId} فعال بوده‌اند...");

        // پیدا کردن کاربرانی که در بازه زمانی مشخص شده فایلی دریافت کرده‌اند
        $userIds = ContentUserProgress::where('bot_id', $botId)
            ->where('updated_at', '>=', $dateThreshold)
            ->pluck('bot_user_id')
            ->unique();

        if ($userIds->isEmpty()) {
            $this->warn("⚠️ هیچ کاربری در {$days} روز گذشته فعالیتی نداشته است.");
            return 0;
        }

        $this->info("✅ تعداد " . $userIds->count() . " کاربر فعال پیدا شد. شروع ارسال...");

        $successCount = 0;
        
        $bar = $this->output->createProgressBar($userIds->count());
        $bar->start();

        foreach ($userIds as $userId) {
            try {
                Artisan::call('user:progress-report', [
                    '--user-id' => $userId,
                    '--bot-id' => $botId
                ]);
                $successCount++;
            } catch (\Exception $e) {
                $this->error("\n❌ خطا در ارسال برای کاربر {$userId}: " . $e->getMessage());
            }
            
            $bar->advance();
            // توقف کوتاه برای جلوگیری از بلاک شدن توسط تلگرام/بله (Rate Limit)
            usleep(200000); // 0.2 seconds
        }

        $bar->finish();
        $this->info("\n🎉 پایان عملیات. کارنامه به {$successCount} کاربر با موفقیت ارسال شد.");
        
        return 0;
    }
}
