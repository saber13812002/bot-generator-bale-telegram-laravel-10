<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\WebhookEndpoint;
use App\Modules\BotOwner\Contracts\BotLibraryServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BotLibraryService implements BotLibraryServiceInterface
{
    public function getBots(BotOwner $owner, ?string $type = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Bot::where('bot_owner_id', $owner->id)
            ->with('webhookEndpoint')
            ->orderByDesc('id');

        if ($type) {
            $query->where('endpoint_id', $type);
        }

        return $query->paginate($perPage)
            ->withQueryString();
    }

    public function getOwnerBotTypes(BotOwner $owner): array
    {
        $endpointIds = Bot::where('bot_owner_id', $owner->id)
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
