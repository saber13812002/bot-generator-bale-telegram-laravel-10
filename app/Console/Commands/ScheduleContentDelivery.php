<?php

namespace App\Console\Commands;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentCategory;
use App\Models\ContentUserProgress;
use App\Services\ContentDeliveryServiceImpl;
use App\Services\ContentQueueServiceImpl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class ScheduleContentDelivery extends Command
{
    protected $signature = 'content:deliver-hourly
                            {--dry-run : فقط نمایش اطلاعات، بدون ارسال}
                            {--bot-id= : فقط برای ربات خاص}
                            {--category-id= : فقط برای دسته خاص}';

    protected $description = 'ارسال ساعتی پادکست به کاربرانی که در صف محتوا هستند';

    public function handle(): int
    {
        $this->info('📦 [ContentDelivery] Starting hourly delivery...');

        $contentEndpoints = config('content_bots.content_endpoint_ids', ['book-library']);
        $bots = Bot::whereIn('endpoint_id', $contentEndpoints)->get();

        if ($bots->isEmpty()) {
            $this->warn('No content bots found.');
            return 0;
        }

        $queueService = app(ContentQueueServiceImpl::class);
        $deliveryService = app(ContentDeliveryServiceImpl::class);
        $dryRun = $this->option('dry-run');
        $specificBotId = $this->option('bot-id');
        $specificCategoryId = $this->option('category-id');
        $totalDelivered = 0;

        foreach ($bots as $botModel) {
            if ($specificBotId && (int) $specificBotId !== $botModel->id) {
                continue;
            }

            $botId = $botModel->id;
            $token = $botModel->bale_bot_token ?? $botModel->telegram_bot_token;
            if (!$token) {
                continue;
            }

            $origin = $botModel->bale_bot_token ? 'bale' : 'telegram';
            $bot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            // دریافت تمام دسته‌های فعال این ربات
            $categories = ContentCategory::where('bot_id', $botId)
                ->where('is_active', true)
                ->when($specificCategoryId, fn($q) => $q->where('id', $specificCategoryId))
                ->get();

            foreach ($categories as $category) {
                // دریافت کاربرانی که در این دسته پیشرفت دارند
                $progressRecords = ContentUserProgress::where('category_id', $category->id)
                    ->where('bot_id', $botId)
                    ->where('last_position', '>', 0)
                    ->with('botUser')
                    ->get();

                foreach ($progressRecords as $progress) {
                    $botUser = $progress->botUser;
                    if (!$botUser) {
                        continue;
                    }

                    // چک کردن اینکه آخرین ارسال حداقل ۱ ساعت گذشته
                    if ($progress->updated_at && $progress->updated_at->gt(now()->subHour())) {
                        continue;
                    }

                    // پیدا کردن آیتم بعدی
                    $nextItem = $queueService->getNextItemForUser($botUser, $category->id, $botId);

                    if (!$nextItem) {
                        // اطلاع به کاربر: محتوای بعدی وجود ندارد
                        if (!$dryRun) {
                            $this->notifyNoContent($bot, $botUser, $category, $botModel, $origin);
                        }
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [DRY-RUN] Would deliver '{$nextItem->title}' to user {$botUser->chat_id}");
                        continue;
                    }

                    try {
                        $delivered = $deliveryService->deliverNextInCategory(
                            $bot, $botUser, $nextItem, $botId, $origin
                        );

                        if ($delivered) {
                            $queueService->advanceProgress($botUser, $category->id, $botId, $nextItem);
                            $totalDelivered++;
                            $this->line("  ✅ Delivered '{$nextItem->title}' to {$botUser->chat_id}");
                        }
                    } catch (\Throwable $e) {
                        Log::error('❌ [ScheduleContentDelivery] Failed', [
                            'user' => $botUser->chat_id,
                            'item' => $nextItem->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->info("📦 [ContentDelivery] Done. Total delivered: {$totalDelivered}");
        return 0;
    }

    private function notifyNoContent(Telegram $bot, BotUsers $botUser, ContentCategory $category, Bot $botModel, string $origin): void
    {
        $chatId = $botUser->chat_id;
        $message = "📭 محتوای جدیدی در دسته «{$category->title}» برای ارسال وجود ندارد.\n";
        $message .= "به زودی محتوای جدید اضافه خواهد شد.";

        BotHelper::sendMessageByChatId($bot, $chatId, $message);

        // اطلاع به ادمین مادر
        $botName = $botModel->bale_bot_name ?: $botModel->telegram_bot_name ?: 'ربات';
        $adminMessage = "⚠️ ربات «{$botName}» (Bot ID: {$botModel->id})\n";
        $adminMessage .= "برای کاربر {$botUser->chat_id} در دسته «{$category->title}» محتوایی در صف ندارد.\n";
        $adminMessage .= "⏰ زمان: " . now()->format('Y-m-d H:i');

        $this->notifyAdmins($adminMessage);
    }

    private function notifyAdmins(string $message): void
    {
        $configs = [
            ['token' => env('BOT_MOTHER_TOKEN_BALE'), 'type' => 'bale'],
            ['token' => env('BOT_MOTHER_TOKEN_TELEGRAM'), 'type' => 'telegram'],
        ];

        foreach (AdminHelper::getAdmins() as $chatId) {
            if (empty($chatId)) continue;
            foreach ($configs as $config) {
                if (empty($config['token'])) continue;
                try {
                    $bot = $config['type'] === 'bale'
                        ? new Telegram($config['token'], 'bale')
                        : new Telegram($config['token']);
                    BotHelper::sendMessageByChatId($bot, $chatId, $message);
                } catch (\Throwable $e) {
                    Log::warning('[ScheduleContentDelivery] Admin notify failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
