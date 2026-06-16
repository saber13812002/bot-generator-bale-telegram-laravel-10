<?php

namespace App\Modules\BotOwner\Services;

use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;
use App\Modules\BotOwner\Models\BotOwnerProRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BotOwnerProService implements BotOwnerProServiceInterface
{
    public const ALLOWED_MONTHS = [0, 3, 6, 12];

    public function __construct(
        private readonly BotOwnerProNotificationService $notificationService,
    ) {
    }

    public static function validateMonths(int $months): array
    {
        if (!in_array($months, self::ALLOWED_MONTHS, true)) {
            return [false, 'Invalid months value. Allowed: 0, 3, 6, 12'];
        }

        return [true, ''];
    }

    public static function expiresAtForMonths(int $months): ?Carbon
    {
        return match ($months) {
            0 => null,
            default => now()->addMonths($months),
        };
    }

    public function requestPro(int $botOwnerId): array
    {
        $owner = BotOwner::find($botOwnerId);
        if (!$owner) {
            return ['success' => false, 'message' => trans('bot-owner.owner_not_found')];
        }

        if ($owner->hasActivePro()) {
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

    public function confirmPro(int $requestId, ?int $adminId = null, int $months = 3): bool
    {
        [$valid] = self::validateMonths($months);
        if (!$valid) {
            return false;
        }

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
        $owner->pro_expires_at = self::expiresAtForMonths($months);
        $owner->save();

        $request->status = 'confirmed';
        $request->approved_by = $adminId;
        $request->approved_at = now();
        $request->save();

        Log::info('BotOwner Pro confirmed', [
            'request_id' => $requestId,
            'bot_owner_id' => $owner->id,
            'admin_id' => $adminId,
            'months' => $months,
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
