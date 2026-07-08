<?php

namespace App\Modules\BotOwner\Contracts;

use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BotLibraryServiceInterface
{
    /**
     * Get paginated bots the owner has access to (owned + admin panel),
     * optionally filtered by endpoint type and relation.
     *
     * @param BotOwner $owner
     * @param string|null $type endpoint_id filter
     * @param string|null $relation 'owner', 'admin', or null for all
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getBots(BotOwner $owner, ?string $type = null, ?string $relation = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get distinct bot types (endpoint_id, name) that this owner has access to.
     *
     * @param BotOwner $owner
     * @return array<int, array{id: string, name: string}>
     */
    public function getOwnerBotTypes(BotOwner $owner): array;
}
