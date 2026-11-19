<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\WebhookEndpoint;
use App\Modules\BotOwner\Contracts\BotLibraryServiceInterface;
use App\Modules\BotOwner\Models\BotAdminPanelUser;
use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BotLibraryService implements BotLibraryServiceInterface
{
    public function getBots(BotOwner $owner, ?string $type = null, ?string $relation = null, int $perPage = 15): LengthAwarePaginator
    {
        $adminBotIds = BotAdminPanelUser::where('bot_owner_id', $owner->id)
            ->pluck('bot_id')
            ->toArray();

        $query = Bot::with('webhookEndpoint')
            ->where(function ($q) use ($owner, $adminBotIds, $relation) {
                if ($relation === 'owner') {
                    // Only directly owned bots
                    $q->where('bot_owner_id', $owner->id);
                } elseif ($relation === 'admin') {
                    // Only admin panel bots
                    $q->whereIn('id', $adminBotIds);
                } else {
                    // All accessible bots (owned OR admin panel)
                    $q->where('bot_owner_id', $owner->id)
                      ->orWhereIn('id', $adminBotIds);
                }
            })
            ->orderByDesc('id');

        if ($type) {
            $query->where('endpoint_id', $type);
        }

        return $query->paginate($perPage)
            ->withQueryString();
    }

    public function getOwnerBotTypes(BotOwner $owner): array
    {
        $adminBotIds = BotAdminPanelUser::where('bot_owner_id', $owner->id)
            ->pluck('bot_id')
            ->toArray();

        $endpointIds = Bot::where(function ($q) use ($owner, $adminBotIds) {
                $q->where('bot_owner_id', $owner->id)
                  ->orWhereIn('id', $adminBotIds);
            })
            ->whereNotNull('endpoint_id')
            ->distinct()
            ->pluck('endpoint_id');

        if ($endpointIds->isEmpty()) {
            return [];
        }

        $endpoints = WebhookEndpoint::whereIn('endpoint_id', $endpointIds)
            ->get()
            ->keyBy('endpoint_id');

        return $endpointIds->map(fn($id) => [
            'id' => $id,
            'name' => $endpoints->has($id) ? $endpoints->get($id)->name : $id,
        ])->values()->toArray();
    }
}
