<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\LibraryPlanRequest;
use App\Modules\BotOwner\Contracts\BotPlanServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;

class BotPlanService implements BotPlanServiceInterface
{
    public function getPendingRequests(Bot $bot): \Illuminate\Support\Collection
    {
        return LibraryPlanRequest::where('bot_id', $bot->id)
            ->pending()
            ->with('botUser')
            ->orderByDesc('created_at')
            ->get();
    }

    public function approve(LibraryPlanRequest $planRequest, BotOwner $owner): array
    {
        if ($planRequest->status !== 'pending') {
            return ['success' => false, 'message' => trans('bot-owner.pro_already_processed')];
        }

        try {
            $planRequest->update([
                'status' => 'approved',
                'approved_by' => $owner->name ?? $owner->phone,
                'approved_at' => now(),
            ]);

            // TODO: Activate the plan for the user in the library subscription system

            return ['success' => true, 'message' => trans('bot-owner.plan_approved')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.plan_approve_failed')];
        }
    }

    public function reject(LibraryPlanRequest $planRequest, BotOwner $owner): array
    {
        if ($planRequest->status !== 'pending') {
            return ['success' => false, 'message' => trans('bot-owner.pro_already_processed')];
        }

        try {
            $planRequest->update([
                'status' => 'rejected',
                'approved_by' => $owner->name ?? $owner->phone,
                'approved_at' => now(),
            ]);

            return ['success' => true, 'message' => trans('bot-owner.plan_rejected')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.plan_reject_failed')];
        }
    }
}
