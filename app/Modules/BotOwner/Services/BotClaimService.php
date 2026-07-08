<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Models\BotOwnershipClaim;
use App\Modules\BotOwner\Contracts\BotClaimServiceInterface;
use App\Modules\BotOwner\Models\BotAdminPanelUser;
use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Support\Str;

class BotClaimService implements BotClaimServiceInterface
{
    public function getClaimableBots(BotOwner $owner): \Illuminate\Support\Collection
    {
        // Bots that don't have a bot_owner_id yet, OR are not linked to this owner
        return Bot::whereNull('bot_owner_id')
            ->orWhere('bot_owner_id', '!=', $owner->id)
            ->where(function ($q) use ($owner) {
                // Exclude bots already claimed by this user or where they're already admin
                $q->whereNotIn('id', function ($sub) use ($owner) {
                    $sub->select('bot_id')
                        ->from('bot_ownership_claims')
                        ->where('bot_owner_id', $owner->id)
                        ->where('status', 'verified');
                });
            })
            ->where(function ($q) use ($owner) {
                // Exclude bots where user is already a panel admin
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

        // Check if already claimed
        $existing = BotOwnershipClaim::where('bot_owner_id', $owner->id)
            ->where('bot_id', $botId)
            ->where('status', 'pending')
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
            'message' => 'Claim generated. Send the verification code to the bot on the messenger.',
            'claim' => $claim,
        ];
    }

    public function getPendingClaims(BotOwner $owner): \Illuminate\Support\Collection
    {
        return BotOwnershipClaim::where('bot_owner_id', $owner->id)
            ->where('status', 'pending')
            ->with('bot')
            ->get();
    }

    public function verifyClaim(string $code, string $chatId, string $origin): array
    {
        $claim = BotOwnershipClaim::where('verification_code', $code)
            ->where('status', 'pending')
            ->first();

        if (!$claim) {
            return ['success' => false, 'message' => 'Invalid or expired verification code.'];
        }

        if ($claim->isExpired()) {
            $claim->update(['status' => 'expired']);
            return ['success' => false, 'message' => 'Verification code has expired. Please generate a new one.'];
        }

        $bot = $claim->bot;
        if (!$bot) {
            return ['success' => false, 'message' => 'Bot not found.'];
        }

        // --- Verification Logic ---

        $verified = false;
        $verifiedAs = null;

        // Check 1: Is the sender the bot owner? (match chat_id)
        $ownerChatId = $origin === 'bale' ? $bot->bale_owner_chat_id : $bot->telegram_owner_chat_id;
        if ($ownerChatId && (string)$ownerChatId === (string)$chatId) {
            $verified = true;
            $verifiedAs = 'owner';
        }

        // Check 2: Is the sender an approved admin?
        if (!$verified) {
            $isAdmin = BotAdminKieRequest::where('bot_id', $bot->id)
                ->where('chat_id', $chatId)
                ->where('origin', $origin)
                ->where('status', 'approved')
                ->exists();

            if ($isAdmin) {
                $verified = true;
                $verifiedAs = 'admin';
            }
        }

        if (!$verified) {
            return [
                'success' => false,
                'message' => 'Verification failed. Your chat ID does not match the bot owner or an approved admin for this bot.',
            ];
        }

        // --- Apply the claim ---
        $claim->update([
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        if ($verifiedAs === 'owner') {
            // Set bot_owner_id (only if not already set)
            if (!$bot->bot_owner_id) {
                $bot->update(['bot_owner_id' => $claim->bot_owner_id]);
            }
        } elseif ($verifiedAs === 'admin') {
            // Add as panel admin (if not already)
            $alreadyAdmin = BotAdminPanelUser::where('bot_id', $bot->id)
                ->where('bot_owner_id', $claim->bot_owner_id)
                ->exists();

            if (!$alreadyAdmin) {
                BotAdminPanelUser::create([
                    'bot_id' => $bot->id,
                    'bot_owner_id' => $claim->bot_owner_id,
                    'added_by_owner_id' => null, // System-verified
                ]);
            }
        }

        $roleText = $verifiedAs === 'owner' ? 'owner' : 'admin';
        return [
            'success' => true,
            'message' => "✅ Bot claimed successfully as {$roleText}! You can now manage it from the web panel.",
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
