<?php

namespace App\Services;

use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Models\BotUsers;
use App\Models\LibraryPlanRequest;
use App\Models\LibraryUserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        DB::transaction(function () use ($request, $planConfig, $approvedBy) {
            $request->update([
                'status' => 'confirmed',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            LibraryUserSubscription::updateOrCreate(
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
        });

        return ['success' => true, 'message' => trans('book_library.plan_confirmed')];
    }
}
