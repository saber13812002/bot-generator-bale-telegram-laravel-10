<?php

namespace App\Jobs;

use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryUserSubscription;
use App\Models\ContentCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram;

class NotifyNewCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $categoryId,
        public int $botId,
        public string $origin
    ) {}

    public function handle(): void
    {
        $category = ContentCategory::find($this->categoryId);
        $botModel = Bot::find($this->botId);
        if (!$category || !$botModel) {
            return;
        }

        $token = $this->origin === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
        if (!$token) {
            return;
        }

        $bot = $this->origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
        $message = trans('book_library.new_category_broadcast', ['category' => $category->title]);

        $users = BotUsers::where('status', 'active')
            ->where('origin', $this->origin)
            ->whereIn('id', LibraryUserSubscription::where('bot_id', $this->botId)->pluck('bot_user_id'))
            ->get();
        foreach ($users as $user) {
            try {
                BotHelper::sendMessageByChatId($bot, $user->chat_id, $message);
            } catch (\Exception $e) {
                Log::warning('NotifyNewCategoryJob failed for user', ['chat_id' => $user->chat_id]);
            }
        }
    }
}
