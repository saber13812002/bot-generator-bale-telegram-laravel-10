<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Interfaces\Services\LibraryMilestoneService;
use App\Models\BotUsers;
use App\Models\LibraryDiscountCode;
use App\Models\LibraryUserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram;

class LibraryMilestoneServiceImpl implements LibraryMilestoneService
{
    public function registerDelivery(
        Telegram $bot,
        BotUsers $botUser,
        int $botId,
        string $origin,
        string $chatId,
        bool $viaReader = false
    ): void {
        if (!config('book_library.rewards.enabled', true)) {
            return;
        }

        // Lock the subscription row so concurrent deliveries cannot double-grant.
        $subscription = LibraryUserSubscription::where('bot_user_id', $botUser->id)
            ->where('bot_id', $botId)
            ->lockForUpdate()
            ->first();

        if (!$subscription || !$subscription->milestoneReached()) {
            return;
        }

        $target = (int) $subscription->reward_target;
        $bonus = $target * (int) config('book_library.rewards.bonus_multiplier', 2);
        $discountAmount = (int) config('book_library.rewards.discount.display_amount', 5000000);

        // ---- Win moment: exactly once, inside a transaction ----
        DB::transaction(function () use ($subscription, $target, $bonus, $botId, $botUser) {
            // Re-check under the row lock (another delivery may have fired first)
            $subscription->refresh();
            if (!$subscription->milestoneReached()) {
                return;
            }

            // 1) Apply the 100% code effect: library free to the end
            $unlimitedLimit = (int) config('book_library.plans.unlimited.limit', 999999);
            $subscription->update([
                'plan' => 'unlimited',
                'books_limit' => $unlimitedLimit,
                'reward_bonus' => $bonus,
                'reward_granted_at' => now(),
                'status' => 'active',
            ]);

            // 2) Symbolic auto-activated 100% discount code (demo)
            LibraryDiscountCode::create([
                'bot_user_id' => $botUser->id,
                'bot_id' => $botId,
                'code' => LibraryDiscountCode::generateCode(),
                'percent' => (int) config('book_library.rewards.discount.percent', 100),
                'display_amount' => (int) config('book_library.rewards.discount.display_amount', 5000000),
                'source' => 'milestone',
                'auto_activated' => true,
                'activated_at' => now(),
                'status' => 'active',
            ]);

            Log::info('🎉 [LibraryMilestone] Reward granted', [
                'bot_id' => $botId,
                'bot_user_id' => $botUser->id,
                'target' => $target,
                'bonus' => $bonus,
            ]);
        });

        // 3) Celebration message (never block delivery on a send failure)
        try {
            $code = LibraryDiscountCode::where('bot_user_id', $botUser->id)
                ->where('bot_id', $botId)
                ->where('source', 'milestone')
                ->latest('created_at')
                ->value('code');

            $message = trans('book_library.milestone_win', [
                'target' => $target,
                'bonus' => $bonus,
                'discount' => number_format($discountAmount),
                'code' => $code ?? '—',
            ]);

            BotHelper::sendMessageByChatId($bot, $chatId, $message);
        } catch (\Throwable $e) {
            Log::error('❌ [LibraryMilestone] Win message failed', [
                'error' => $e->getMessage(),
                'bot_id' => $botId,
                'chat_id' => $chatId,
            ]);
        }
    }
}
