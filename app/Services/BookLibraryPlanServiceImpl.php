<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\LibraryPlanRequest;
use App\Models\LibraryUserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram;

class BookLibraryPlanServiceImpl implements BookLibraryPlanService
{
    public function __construct(
        private BookLibraryService $bookLibraryService,
        private LibraryPlanNotificationService $notificationService
    ) {}

    public function canDeliver(BotUsers $botUser, int $botId): bool
    {
        $subscription = $this->bookLibraryService->getOrCreateSubscription($botUser, $botId);
        return $subscription->canDeliver();
    }

    public function requestPlanUpgrade(BotUsers $botUser, int $botId, string $plan, string $userIdentifier): array
    {
        $plans = config('book_library.plans', []);
        if (!isset($plans[$plan]) || $plan === 'free') {
            return ['success' => false, 'message' => trans('book_library.plan_invalid')];
        }

        $existing = LibraryPlanRequest::where('bot_user_id', $botUser->id)
            ->where('bot_id', $botId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'message' => trans('book_library.plan_pending'),
                'request_id' => $existing->id,
            ];
        }

        $request = LibraryPlanRequest::create([
            'bot_user_id' => $botUser->id,
            'bot_id' => $botId,
            'plan' => $plan,
            'user_identifier' => $userIdentifier,
            'status' => 'pending',
        ]);

        $this->notificationService->notifyAdmins($request);

        Log::info('💳 [BookLibrary] Plan request created', [
            'request_id' => $request->id,
            'plan' => $plan,
            'bot_id' => $botId,
        ]);

        return [
            'success' => true,
            'request_id' => $request->id,
            'message' => trans('book_library.plan_requested'),
        ];
    }

    public function confirmPlanRequest(int $requestId, ?string $approvedBy = null): array
    {
        $request = LibraryPlanRequest::find($requestId);
        if (!$request || $request->status !== 'pending') {
            return ['success' => false, 'message' => trans('book_library.plan_not_found')];
        }

        $planConfig = config('book_library.plans.' . $request->plan);
        if (!$planConfig) {
            return ['success' => false, 'message' => trans('book_library.plan_invalid')];
        }

        // Milestone reward: arm a target for paid plans that have rewards enabled.
        // free/unlimited (and plans outside the rewards list) never arm.
        $rewardTarget = $this->rewardTargetForPlan($request->plan);

        DB::transaction(function () use ($request, $planConfig, $approvedBy, $rewardTarget) {
            $request->update([
                'status' => 'confirmed',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            $subscription = LibraryUserSubscription::updateOrCreate(
                [
                    'bot_user_id' => $request->bot_user_id,
                    'bot_id' => $request->bot_id,
                ],
                [
                    'plan' => $request->plan,
                    'books_limit' => $planConfig['limit'],
                    'status' => 'active',
                ]
            );

            // A confirmed (re)purchase re-arms the milestone so the reward
            // can fire again on the new plan. Plans without a reward
            // (free/unlimited) clear any previously armed milestone.
            if ($rewardTarget !== null) {
                $subscription->armMilestone($rewardTarget);
                $subscription->save();
            } else {
                $subscription->update([
                    'reward_target' => null,
                    'reward_bonus' => null,
                    'reward_granted_at' => null,
                ]);
            }
        });

        // Notify the user regardless of the approval channel
        // (Nova bulk, Nova single three-dot, bot command).
        $this->notifyUserPlanActivated($request, $rewardTarget);

        Log::info('💎 [BookLibrary] Plan request confirmed', [
            'request_id' => $requestId,
            'plan' => $request->plan,
            'reward_target' => $rewardTarget,
            'approved_by' => $approvedBy,
        ]);

        return ['success' => true, 'message' => trans('book_library.plan_confirmed')];
    }

    /**
     * Milestone target for a plan (null = no milestone).
     */
    private function rewardTargetForPlan(string $plan): ?int
    {
        if (!config('book_library.rewards.enabled', true)) {
            return null;
        }

        $rewardPlans = config('book_library.rewards.plans', []);
        if (!in_array($plan, $rewardPlans, true)) {
            return null;
        }

        $planConfig = config('book_library.plans.' . $plan);
        return $planConfig ? (int) $planConfig['limit'] : null;
    }

    /**
     * Send the "plan activated (+ reward teaser)" message to the user's
     * chat, origin-aware (bale vs telegram token). Never throws.
     */
    private function notifyUserPlanActivated(LibraryPlanRequest $request, ?int $rewardTarget): void
    {
        try {
            $botUser = $request->botUser;
            $bot = $request->bot;
            if (!$botUser || !$bot) {
                Log::warning('⚠️ [BookLibrary] Plan confirmed but botUser/bot missing; skipping user notification', [
                    'request_id' => $request->id,
                ]);
                return;
            }

            $origin = $botUser->origin ?? 'telegram';
            $token = $origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
            if (!$token) {
                Log::warning('⚠️ [BookLibrary] No bot token for origin; skipping user notification', [
                    'request_id' => $request->id,
                    'origin' => $origin,
                    'bot_id' => $bot->id,
                ]);
                return;
            }

            $userBot = $this->createMessenger($token, $origin === 'bale' ? 'bale' : null);

            $message = trans('book_library.plan_activated');
            if ($rewardTarget !== null) {
                $bonus = $rewardTarget * (int) config('book_library.rewards.bonus_multiplier', 2);
                $message .= "\n\n" . trans('book_library.milestone_teaser', [
                    'target' => $rewardTarget,
                    'bonus' => $bonus,
                ]);
            }

            BotHelper::sendMessageByChatId($userBot, $botUser->chat_id, $message);
        } catch (\Throwable $e) {
            Log::error('❌ [BookLibrary] Plan activation notification failed', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Messenger factory (overridable in tests to avoid real API calls).
     */
    protected function createMessenger(string $token, ?string $origin): Telegram
    {
        return $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }
}
