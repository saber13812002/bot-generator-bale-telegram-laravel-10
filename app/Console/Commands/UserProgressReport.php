<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentItem;
use App\Models\ContentUserProgress;
use App\Models\LibraryBotConfig;
use Telegram;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Generate a progress report for a user and send it via the user's bot.
 *
 * Single-bot mode (default): the report is scoped to the requesting bot and
 * its paired library/reader bot (via LibraryBotConfig), so each bot's
 * /dailyreport shows only that bot's activity.
 *
 * All-bots mode (--all-bots): report grouped per bot for the super admin.
 */
class UserProgressReport extends Command
{
    protected $signature = 'user:progress-report
                            {--user-id= : ID of the BotUsers record (required)}
                            {--bot-id= : ID of the specific Bot to send from (optional)}
                            {--all-bots= : Generate a report across all bots (super admin)}
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

        $allBots = (bool) $this->option('all-bots');
        $senderBotId = (int) ($this->option('bot-id') ?: $user->bot_id);

        // Gather progress data
        // در حالت تک‌ربات: فقط رکورد همان ربات (و ربات جفتش) — تا گزارش هر ربات
        // فقط فعالیت خودش را نشان بدهد (رفع باگ نمایش گزارش ربات اول در ربات دوم)
        $query = ContentUserProgress::with('category.items')->where('bot_user_id', $user->id);
        if (!$allBots) {
            $query->whereIn('bot_id', $this->botScopeIds($senderBotId));
        }
        $progressRecords = $query->get();

        // Group by bot so all-bots mode can render a per-bot section
        $byBot = $progressRecords->groupBy('bot_id');

        $totalCategories = $progressRecords->unique('category_id')->count();
        $totalReceived = (int) $progressRecords->sum('last_position');

        $categoryBreakdown = '';
        foreach ($byBot as $botId => $botRecords) {
            if ($allBots) {
                $categoryBreakdown .= "🤖 **{$this->resolveBotName((int) $botId)}** (ID: {$botId})\n\n";
            }
            $categoryBreakdown .= $this->buildCategoryBreakdown($botRecords);
        }

        if (empty($categoryBreakdown)) {
            $categoryBreakdown = "🔸 هنوز فایلی دریافت نکرده‌اید.\n";
        }

        if ($allBots) {
            $report = "📊 **گزارش فعالیت در همه‌ی ربات‌ها**\n\n";
            $report .= "- کل دسته‌ها (در همه‌ی ربات‌ها): {$totalCategories}\n";
            $report .= "- کل فایل‌های دریافتی: {$totalReceived}\n\n";
            $report .= "### جزئیات هر ربات:\n\n";
            $report .= $categoryBreakdown;
        } else {
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
        }

        $report .= \App\Helpers\BotHelper::getRandomHelpCta($user);

        // Determine bot token
        $bot = Bot::find($senderBotId);

        if (!$bot) {
            \Illuminate\Support\Facades\Log::error("UserProgressReport: Bot not found for botId '{$senderBotId}' (userId '{$userId}')");
            $this->error('❌ ربات مربوط به کاربر یافت نشد');
            return 1;
        }

        $token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
        $type = $bot->type ?? 'bale';

        $messenger = new Telegram($token, $type);
        BotHelper::sendMessageByChatId($messenger, $user->chat_id, $report);

        $this->info('✅ گزارش به کاربر ارسال شد');
        \Illuminate\Support\Facades\Log::info("UserProgressReport: Sent report to user {$user->id} via bot {$bot->id}" . ($allBots ? ' (all-bots mode)' : ''));
        return 0;
    }

    /**
     * Build the per-category breakdown for a group of progress records.
     */
    private function buildCategoryBreakdown(Collection $botRecords): string
    {
        $breakdown = '';
        foreach ($botRecords as $progress) {
            $categoryName = $progress->category->title ?? 'دسته نامشخص';
            $received = (int) $progress->last_position;
            $totalItemsInCategory = $progress->category ? (int) $progress->category->items->count() : 0;
            $remaining = max(0, $totalItemsInCategory - $received);

            $statusIcon = $remaining === 0 ? '✅' : '⏳';
            $breakdown .= "🔹 **{$categoryName}**\n";
            $breakdown .= "   👁 دیده‌شده: {$received} / ⏳ باقی‌مانده: {$remaining}\n";

            $lastItem = $progress->last_content_item_id ? ContentItem::find((int) $progress->last_content_item_id) : null;
            if ($lastItem) {
                $sentAt = $progress->updated_at ? $progress->updated_at->format('Y/m/d H:i') : '—';
                $breakdown .= "   🔙 آخرین فایل: «{$lastItem->title}» (ارسال: {$sentAt})\n";
            }

            if ($remaining > 0) {
                $breakdown .= "   👉 **دریافت قسمت بعدی:** /category{$progress->category_id}\n\n";
            } else {
                $breakdown .= "   {$statusIcon} _پایان یافته (تمام فایل‌ها دریافت شد)_\n\n";
            }
        }
        return $breakdown;
    }

    /**
     * Bot ids whose progress belongs to the same library as $senderBotId:
     * the sender itself plus its LibraryBotConfig counterpart (main <-> reader).
     */
    private function botScopeIds(int $senderBotId): array
    {
        $ids = [(int) $senderBotId];

        $asMain = LibraryBotConfig::where('bot_id', $senderBotId)->first();
        if ($asMain) {
            $ids[] = (int) $asMain->reader_bot_id;
        }

        $asReader = LibraryBotConfig::where('reader_bot_id', $senderBotId)->first();
        if ($asReader) {
            $ids[] = (int) $asReader->bot_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function resolveBotName(int $botId): string
    {
        $bot = Bot::find($botId);
        if (!$bot) {
            return "Bot ID: {$botId}";
        }

        return (string) (
            $bot->bale_bot_name
            ?? $bot->telegram_bot_name
            ?? $bot->soroush_bot_name
            ?? $bot->rubika_bot_name
            ?? "Bot ID: {$botId}"
        );
    }
}
