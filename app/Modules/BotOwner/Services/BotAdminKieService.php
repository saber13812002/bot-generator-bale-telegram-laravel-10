<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Modules\BotOwner\Contracts\BotAdminKieServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;

class BotAdminKieService implements BotAdminKieServiceInterface
{
    public function getPendingRequests(Bot $bot): \Illuminate\Support\Collection
    {
        return BotAdminKieRequest::where('bot_id', $bot->id)
            ->pending()
            ->orderByDesc('created_at')
            ->get();
    }

    public function approve(BotAdminKieRequest $request, BotOwner $owner): array
    {
        if ($request->status !== 'pending') {
            return ['success' => false, 'message' => trans('bot-owner.pro_already_processed')];
        }

        try {
            $request->update([
                'status' => 'approved',
                'approved_by' => $owner->name ?? $owner->phone,
                'approved_at' => now(),
            ]);

            // TODO: Notify the applicant via bot that they've been approved as admin

            return ['success' => true, 'message' => trans('bot-owner.admin_kie_approved')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.admin_kie_approve_failed')];
        }
    }

    public function reject(BotAdminKieRequest $request, BotOwner $owner): array
    {
        if ($request->status !== 'pending') {
            return ['success' => false, 'message' => trans('bot-owner.pro_already_processed')];
        }

        try {
            $request->update([
                'status' => 'rejected',
                'approved_by' => $owner->name ?? $owner->phone,
                'approved_at' => now(),
            ]);

            return ['success' => true, 'message' => trans('bot-owner.admin_kie_rejected')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.admin_kie_reject_failed')];
        }
    }
}
