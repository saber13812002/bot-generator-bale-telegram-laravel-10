<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentUserProgress;
use Telegram;
use Illuminate\Console\Command;

/**
 * Generate a progress report for a user and send it via the user's bot.
 */
class UserProgressReport extends Command
{
    protected $signature = 'user:progress-report
                            {--user-id= : ID of the BotUsers record (required)}
                            {--bot-id= : ID of the specific Bot to send from (optional)}
                            {--format=markdown : Output format (markdown or text)}';
    protected $description = 'ارسال گزارش پیشرفت کاربر به ربات مربوطه';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        if (!$userId) {
            $this->error('❌ لطفاً شناسه کاربر را با گزینه --user-id مشخص کنید');
            return 1;
        }

        $user = BotUsers::find($userId);
        if (!$user) {
            $this->error("❌ کاربر با شناسه $userId یافت نشد");
            return 1;
        }

        // Gather progress data
        $progressRecords = ContentUserProgress::with('category.items')->where('bot_user_id', $user->id)->get();
        $totalCategories = $progressRecords->unique('category_id')->count();
        $totalReceived = $progressRecords->sum('last_position');

        $categoryBreakdown = "";
        foreach ($progressRecords as $progress) {
            $categoryName = $progress->category->title ?? 'دسته نامشخص';
            $received = $progress->last_position;
            $totalItemsInCategory = $progress->category ? $progress->category->items->count() : 0;
            $remaining = max(0, $totalItemsInCategory - $received);
            
            $categoryBreakdown .= "- 📁 {$categoryName}: دریافت شده {$received} | باقیمانده {$remaining}\n";
        }

        if (empty($categoryBreakdown)) {
            $categoryBreakdown = "- هنوز فایلی دریافت نشده است.\n";
        }

        // Load template if exists
        $templatePath = base_path('docs/PROGRESS_REPORT_TEMPLATE.md');
        if (file_exists($templatePath)) {
            $template = file_get_contents($templatePath);
        } else {
            $template = "## 📊 گزارش پیشرفت کاربر\n\n- شناسه کاربر: {user_id}\n- کل دسته‌ها: {total_categories}\n- کل فایل‌های دریافتی: {total_received}\n\n### جزئیات دسته‌ها:\n{category_breakdown}\n";
        }

        $report = str_replace([
            '{user_id}',
            '{total_categories}',
            '{total_received}',
            '{category_breakdown}'
        ], [
            $user->id,
            $totalCategories,
            $totalReceived,
            $categoryBreakdown
        ], $template);

        // Determine bot token
        $botId = $this->option('bot-id') ?: $user->bot_id;
        $bot = Bot::find($botId);
        
        if (!$bot) {
            \Illuminate\Support\Facades\Log::error("UserProgressReport: Bot not found for botId '{$botId}' (userId '{$userId}')");
            $this->error('❌ ربات مربوط به کاربر یافت نشد');
            return 1;
        }
        
        $token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
        $type = $bot->type ?? 'bale';

        $messenger = new Telegram($token, $type);
        BotHelper::sendMessageByChatId($messenger, $user->chat_id, $report);

        $this->info('✅ گزارش به کاربر ارسال شد');
        \Illuminate\Support\Facades\Log::info("UserProgressReport: Sent report to user {$user->id} via bot {$bot->id}");
        return 0;
    }
}
