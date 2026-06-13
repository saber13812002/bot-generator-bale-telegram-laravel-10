<?php

namespace App\Modules\BotOwner\Contracts;

interface BotOwnerProServiceInterface
{
    /**
     * @return array{success: bool, message: string, request_id?: int}
     */
    public function requestPro(int $botOwnerId): array;

    public function confirmPro(int $requestId, ?int $adminId = null): bool;

    public function rejectPro(int $requestId, ?string $notes = null): bool;
}
