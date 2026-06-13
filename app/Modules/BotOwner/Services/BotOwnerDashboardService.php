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

        return [
            'bots' => $bots,
            'stats' => [
                'total_bots' => $bots->count(),
                'is_pro' => $owner->is_pro,
                'has_pending_pro' => $pendingPro,
            ],
        ];
    }
}
