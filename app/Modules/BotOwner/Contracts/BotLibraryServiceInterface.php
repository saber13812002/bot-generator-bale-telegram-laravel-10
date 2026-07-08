<?php

namespace App\Modules\BotOwner\Contracts;

use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BotLibraryServiceInterface
{
    /**
     * Get paginated bots for the owner, optionally filtered by endpoint type.
     *
     * @param BotOwner $owner
     * @param string|null $type endpoint_id filter
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getBots(BotOwner $owner, ?string $type = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get distinct bot types (endpoint_id, name) that this owner has.
     *
     * @param BotOwner $owner
     * @return array<int, array{id: string, name: string}>
     */
    public function getOwnerBotTypes(BotOwner $owner): array;
}
