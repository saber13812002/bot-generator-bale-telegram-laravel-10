<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Models\BotOwnershipClaim;
use App\Modules\BotOwner\Contracts\BotClaimServiceInterface;
use App\Modules\BotOwner\Models\BotAdminPanelUser;
use App\Modules\BotOwner\Models\BotOwner;

class BotClaimService implements BotClaimServiceInterface
{
    public function getClaimableBots(BotOwner $owner): \Illuminate\Support\Collection
    {
        return Bot::whereNull('bot_owner_id')
            ->orWhere('bot_owner_id', '!=', $owner->id)
            ->where(function ($q) use ($owner) {
                $q->whereNotIn('id', function ($sub) use ($owner) {
                    $sub->select('bot_id')
                        ->from('bot_ownership_claims')
                        ->where('bot_owner_id', $owner->id)
                        ->where('status', 'verified');
                });
            })
            ->where(function ($q) use ($owner) {
                $q->whereNotIn('id', function ($sub) use ($owner) {
                    $sub->select('bot_id')
                        ->from('bot_admin_panel_users')
                        ->where('bot_owner_id', $owner->id);
                });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function generateClaim(BotOwner $owner, int $botId, string $claimType = 'owner'): array
    {
        $bot = Bot::find($botId);
        if (!$bot) {
            return ['success' => false, 'message' => 'Bot not found.'];
        }

        // Check for existing pending claim
        $existing = BotOwnershipClaim::where('bot_owner_id', $owner->id)
            ->where('bot_id', $botId)
            ->whereIn('status', ['pending', 'pending_approval'])
            ->first();

        if ($existing) {
            return [
                'success' => true,
                'message' => 'Existing claim found.',
                'claim' => $existing,
            ];
        }

        // Generate unique code
        do {
            $code = BotOwnershipClaim::generateCode();
        } while (BotOwnershipClaim::where('verification_code', $code)->exists());

        $claim = BotOwnershipClaim::create([
            'bot_owner_id' => $owner->id,
            'bot_id' => $botId,
            'verification_code' => $code,
            'claim_type' => $claimType,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        return [
            'success' => true,
            'message' => 'Claim generated. Send /tome ' . $code . ' to the bot on the messenger.',
            'claim' => $claim,
        ];
    }

    public function getPendingClaims(BotOwner $owner): \Illuminate\Support\Collection
    {
        return BotOwnershipClaim::where('bot_owner_id', $owner->id)
            ->whereIn('status', ['pending', 'pending_approval'])
            ->with('bot')
            ->get();
    }

    public function verifyClaim(string $code, string $chatId, string $origin): array
    {
        $claim = BotOwnershipClaim::where('verification_code', $code)
            ->whereIn('status', ['pending', 'pending_approval'])
            ->first();

        if (!$claim) {
            return ['success' => false, 'message' => '❌ Invalid or expired verification code.'];
        }

        if ($claim->isExpired()) {
            $claim->update(['status' => 'expired']);
            return ['success' => false, 'message' => '❌ Verification code has expired. Please generate a new one from the web panel.'];
        }

        $bot = $claim->bot;
        if (!$bot) {
            return ['success' => false, 'message' => '❌ Bot not found.'];
        }

        // --- Check if sender already has access ---
        $ownerChatId = $origin === 'bale' ? $bot->bale_owner_chat_id : $bot->telegram_owner_chat_id;
        $isOwner = $ownerChatId && (string)$ownerChatId === (string)$chatId;

        $isApprovedAdmin = BotAdminKieRequest::where('bot_id', $bot->id)
            ->where('chat_id', $chatId)
            ->where('origin', $origin)
            ->where('status', 'approved')
            ->exists();

        $isPanelAdmin = BotAdminPanelUser::where('bot_id', $bot->id)
            ->where('bot_owner_id', $claim->bot_owner_id)
            ->exists();

        if ($isOwner || $isApprovedAdmin || $isPanelAdmin) {
            // Already has access — verify the code anyway
            $claim->update(['status' => 'verified', 'verified_at' => now()]);
            return [
                'success' => true,
                'message' => '✅ You already have access to this bot! Code verified.',
            ];
        }

        // --- New user: Create pending admin request ---
        $kieRequest = BotAdminKieRequest::create([
            'bot_id' => $bot->id,
            'chat_id' => $chatId,
            'origin' => $origin,
            'status' => 'pending',
            'notes' => 'Claim via /tome code: ' . $code,
        ]);

        $claim->update(['status' => 'pending_approval']);

        return [
            'success' => true,
            'message' => '📋 Your request has been sent to the bot admin for approval. They will review it from the web panel.',
            'needs_approval' => true,
            'bot' => $bot,
        ];
    }

    public function getAccessibleBotIds(BotOwner $owner): array
    {
        $ownedIds = Bot::where('bot_owner_id', $owner->id)->pluck('id')->toArray();
        $adminIds = BotAdminPanelUser::where('bot_owner_id', $owner->id)
            ->pluck('bot_id')
            ->toArray();

        return array_unique(array_merge($ownedIds, $adminIds));
    }
}
