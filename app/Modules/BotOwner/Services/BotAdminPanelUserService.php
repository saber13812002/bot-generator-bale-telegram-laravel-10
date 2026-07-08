<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Modules\BotOwner\Contracts\BotAdminPanelUserServiceInterface;
use App\Modules\BotOwner\Models\BotAdminPanelUser;
use App\Modules\BotOwner\Models\BotOwner;

class BotAdminPanelUserService implements BotAdminPanelUserServiceInterface
{
    public function getAdmins(Bot $bot): \Illuminate\Support\Collection
    {
        return BotAdminPanelUser::where('bot_id', $bot->id)
            ->with('botOwner')
            ->with('addedBy')
            ->get();
    }

    public function addAdmin(Bot $bot, int $botOwnerId, BotOwner $addedBy): array
    {
        // Cannot add self (owner already has access)
        if ($bot->bot_owner_id === $botOwnerId) {
            return ['success' => false, 'message' => trans('bot-owner.admin_panel_add_self')];
        }

        // Check if already exists
        $exists = BotAdminPanelUser::where('bot_id', $bot->id)
            ->where('bot_owner_id', $botOwnerId)
            ->exists();

        if ($exists) {
            return ['success' => false, 'message' => trans('bot-owner.admin_panel_already_admin')];
        }

        // Verify the bot owner exists
        $ownerToAdd = BotOwner::find($botOwnerId);
        if (!$ownerToAdd) {
            return ['success' => false, 'message' => trans('bot-owner.owner_not_found')];
        }

        try {
            BotAdminPanelUser::create([
                'bot_id' => $bot->id,
                'bot_owner_id' => $botOwnerId,
                'added_by_owner_id' => $addedBy->id,
            ]);

            return ['success' => true, 'message' => trans('bot-owner.admin_panel_added')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.admin_panel_add_failed')];
        }
    }

    public function removeAdmin(Bot $bot, int $adminId, BotOwner $removedBy): array
    {
        $admin = BotAdminPanelUser::where('bot_id', $bot->id)
            ->findOrFail($adminId);

        try {
            $admin->delete();
            return ['success' => true, 'message' => trans('bot-owner.admin_panel_removed')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.admin_panel_remove_failed')];
        }
    }

    public function searchOwners(string $query): \Illuminate\Support\Collection
    {
        return BotOwner::where('phone', 'like', '%' . $query . '%')
            ->orWhere('name', 'like', '%' . $query . '%')
            ->limit(10)
            ->get(['id', 'phone', 'name']);
    }
}
