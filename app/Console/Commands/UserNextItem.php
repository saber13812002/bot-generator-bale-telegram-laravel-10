<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentItem;
use App\Models\ContentUserProgress;
use Telegram;
use Illuminate\Console\Command;

/**
 * Send the next content item to a user based on their progress.
 */
class UserNextItem extends Command
{
    protected $signature = 'user:next-item
                            {--user-id= : ID of the BotUsers record (required)}
                            {--category-id= : Category ID for which to advance (required)}';
    protected $description = 'ارسال آیتم بعدی به کاربر بر اساس پیشرفت فعلی';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        $categoryId = $this->option('category-id');
        if (! $userId || ! $categoryId) {
            $this->error('❌ هر دو گزینه --user-id و --category-id الزامی هستند');
            return 1;
        }

        $user = BotUsers::find($userId);
        if (! $user) {
            $this->error("❌ کاربر با شناسه $userId یافت نشد");
            return 1;
        }

        // Get latest progress for this user & category
        $progress = ContentUserProgress::where('bot_user_id', $user->id)
            ->where('category_id', $categoryId)
            ->orderByDesc('updated_at')
            ->first();

        $lastPosition = $progress ? $progress->last_position : 0;

        // Find next content item (id greater than last_position)
        $nextItem = ContentItem::where('category_id', $categoryId)
            ->where('id', '>', $lastPosition)
            ->orderBy('id')
            ->first();

        if (! $nextItem) {
            $this->info('✅ هیچ آیتم بعدی برای این کاربر/دسته‌بندی یافت نشد');
            return 0;
        }

        // Prepare message (simple example: title + description)
        $message = $nextItem->title ?? 'آیتم جدید';
        if (property_exists($nextItem, 'description') && $nextItem->description) {
            $message .= "\n\n" . $nextItem->description;
        }

        // Determine bot token (same logic as in audio report)
        $bot = Bot::find($user->bot_id);
        if (! $bot) {
            $this->error('❌ ربات مربوط به کاربر یافت نشد');
            return 1;
        }
        $token = $bot->bale_bot_token ?? $bot->telegram_bot_token;
        $type = $bot->type ?? 'bale';

        $messenger = new Telegram($token, $type);
        BotHelper::sendMessageByChatId($messenger, $user->chat_id, $message);

        // Update progress record (set last_position to this item's id)
        if ($progress) {
            $progress->last_position = $nextItem->id;
            $progress->save();
        } else {
            // Create a new progress entry
            ContentUserProgress::create([
                'bot_user_id'    => $user->id,
                'category_id'    => $categoryId,
                'bot_id'         => $bot->id,
                'last_position' => $nextItem->id,
            ]);
        }

        $this->info("✅ آیتم {$nextItem->id} برای کاربر ارسال شد");
        return 0;
    }
}
