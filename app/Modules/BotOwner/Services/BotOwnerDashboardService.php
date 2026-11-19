<?php

namespace App\Modules\BotOwner\Services;

use App\Modules\BotOwner\Contracts\BotOwnerDashboardServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;
use App\Models\Bot;

class BotOwnerDashboardService implements BotOwnerDashboardServiceInterface
{
    public function getDashboardData(BotOwner $owner): array
    {
        $bots = Bot::where('bot_owner_id', $owner->id)
            ->orderByDesc('id')
            ->get();

        $pendingPro = $owner->proRequests()->where('status', 'pending')->exists();
        $isPro = $owner->hasActivePro();

        return [
            'bots' => $bots,
            'stats' => [
                'total_bots' => $bots->count(),
                'is_pro' => $isPro,
                'has_pending_pro' => $pendingPro,
                'pro_expires_at' => $isPro ? $owner->pro_expires_at : null,
                'pro_unlimited' => $isPro && $owner->isProUnlimited(),
            ],
        ];
    }
}
