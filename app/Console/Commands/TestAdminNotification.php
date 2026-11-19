<?php

namespace App\Console\Commands;

use App\Helpers\EmailAdminHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestAdminNotification extends Command
{
    protected $signature = 'email:test-admin-notification 
                            {message : پیام تست برای ارسال به ادمین‌ها}
                            {--type=both : نوع پیام‌رسان (telegram, bale, both)}';

    protected $description = 'تست ارسال پیام به ادمین‌ها';

    public function handle(): int
    {
        $message = $this->argument('message');
        $type = $this->option('type');

        $this->info('🧪 تست ارسال پیام به ادمین‌ها');
        $this->info("📝 پیام: {$message}");
        $this->info("📱 نوع: {$type}");
        $this->newLine();

        try {
            if ($type === 'both' || $type === 'telegram') {
                $this->info('📤 ارسال به Telegram...');
                EmailAdminHelper::sendToAllAdmins($message, 'telegram');
                $this->info('✅ ارسال به Telegram انجام شد');
            }

            if ($type === 'both' || $type === 'bale') {
                $this->info('📤 ارسال به Bale...');
                EmailAdminHelper::sendToAllAdmins($message, 'bale');
                $this->info('✅ ارسال به Bale انجام شد');
            }

            $this->info("\n✅ تست با موفقیت انجام شد!");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ خطا: {$e->getMessage()}");
            Log::error('❌ [TestAdminNotification] Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
