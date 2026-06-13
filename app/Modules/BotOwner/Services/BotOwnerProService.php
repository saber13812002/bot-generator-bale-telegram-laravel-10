<?php

namespace App\Modules\BotOwner\Services;

use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;
use App\Modules\BotOwner\Models\BotOwnerProRequest;
use Illuminate\Support\Facades\Log;

class BotOwnerProService implements BotOwnerProServiceInterface
{
    public function __construct(
        private readonly BotOwnerProNotificationService $notificationService,
    ) {
    }

    public function requestPro(int $botOwnerId): array
    {
        $owner = BotOwner::find($botOwnerId);
        if (!$owner) {
            return ['success' => false, 'message' => trans('bot-owner.owner_not_found')];
        }

        if ($owner->is_pro) {
            return ['success' => false, 'message' => trans('bot-owner.already_pro')];
        }

        $existing = BotOwnerProRequest::where('bot_owner_id', $botOwnerId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'message' => trans('bot-owner.pro_request_pending'),
                'request_id' => $existing->id,
            ];
        }

        $request = BotOwnerProRequest::create([
            'bot_owner_id' => $botOwnerId,
            'status' => 'pending',
        ]);

        $this->notificationService->notifySuperAdmins($request);

        Log::info('BotOwner Pro request created', [
            'request_id' => $request->id,
            'bot_owner_id' => $botOwnerId,
        ]);

        return [
            'success' => true,
            'message' => trans('bot-owner.pro_request_submitted'),
            'request_id' => $request->id,
        ];
    }

    public function confirmPro(int $requestId, ?int $adminId = null): bool
    {
        $request = BotOwnerProRequest::find($requestId);
        if (!$request || $request->status !== 'pending') {
            return false;
        }

        $owner = $request->botOwner;
        if (!$owner) {
            return false;
        }

        $owner->is_pro = true;
        $owner->pro_confirmed_at = now();
        $owner->save();

        $request->status = 'confirmed';
        $request->approved_by = $adminId;
        $request->approved_at = now();
        $request->save();

        Log::info('BotOwner Pro confirmed', [
            'request_id' => $requestId,
            'bot_owner_id' => $owner->id,
            'admin_id' => $adminId,
        ]);

        return true;
    }

    public function rejectPro(int $requestId, ?string $notes = null): bool
    {
        $request = BotOwnerProRequest::find($requestId);
        if (!$request || $request->status !== 'pending') {
            return false;
        }

        $request->status = 'rejected';
        $request->notes = $notes;
        $request->save();

        return true;
    }
}
