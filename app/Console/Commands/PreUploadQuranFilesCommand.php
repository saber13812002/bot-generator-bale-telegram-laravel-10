<?php

namespace App\Console\Commands;

use App\Jobs\PreUploadQuranFilesJob;
use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PreUploadQuranFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quran:pre-upload-files 
                            {--bot-id= : Bot ID to upload files for}
                            {--type= : Bot type (telegram or bale)}
                            {--limit= : Limit number of files to upload}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-upload Quran files (scan pages, audio recitations, audio pages) for bots';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $botId = $this->option('bot-id');
        $botType = $this->option('type');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;

        // اگر bot-id و type مشخص نشده باشند، از همه ربات‌ها استفاده می‌کنیم
        if (!$botId || !$botType) {
            $this->info('📋 Uploading files for all bots...');
            
            $bots = Bot::query()
                ->where(function ($query) {
                    $query->whereNotNull('telegram_bot_token')
                        ->where('telegram_bot_status', 'Active');
                })
                ->orWhere(function ($query) {
                    $query->whereNotNull('bale_bot_token')
                        ->where('bale_bot_status', 'Active');
                })
                ->get();

            $this->info("Found {$bots->count()} active bots");

            foreach ($bots as $bot) {
                // آپلود برای تلگرام
                if ($bot->telegram_bot_token && $bot->telegram_bot_status == 'Active') {
                    $this->info("📤 Uploading files for Telegram bot ID: {$bot->id}");
                    PreUploadQuranFilesJob::dispatch($bot->id, 'telegram', $limit);
                }

                // آپلود برای بله
                if ($bot->bale_bot_token && $bot->bale_bot_status == 'Active') {
                    $this->info("📤 Uploading files for Bale bot ID: {$bot->id}");
                    PreUploadQuranFilesJob::dispatch($bot->id, 'bale', $limit);
                }
            }

            $this->info('✅ Jobs dispatched successfully');
            return 0;
        }

        // اگر bot-id و type مشخص شده باشند، فقط برای آن ربات آپلود می‌کنیم
        $bot = Bot::find($botId);
        if (!$bot) {
            $this->error("❌ Bot with ID {$botId} not found");
            return 1;
        }

        // بررسی token
        if ($botType == 'telegram' && (!$bot->telegram_bot_token || $bot->telegram_bot_status != 'Active')) {
            $this->error("❌ Telegram bot is not active or token not found");
            return 1;
        }

        if ($botType == 'bale' && (!$bot->bale_bot_token || $bot->bale_bot_status != 'Active')) {
            $this->error("❌ Bale bot is not active or token not found");
            return 1;
        }

        $this->info("📤 Uploading files for {$botType} bot ID: {$botId}");
        
        if ($limit) {
            $this->info("📊 Limit: {$limit} files per type");
        }

        // اجرای Job
        PreUploadQuranFilesJob::dispatch($botId, $botType, $limit);

        $this->info('✅ Job dispatched successfully');
        return 0;
    }
}
