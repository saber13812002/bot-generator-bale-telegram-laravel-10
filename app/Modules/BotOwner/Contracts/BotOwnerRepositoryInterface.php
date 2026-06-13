<?php

namespace App\Modules\BotOwner\Contracts;

use App\Modules\BotOwner\Models\BotOwner;

interface BotOwnerRepositoryInterface
{
    public function findByPhone(string $phone): ?BotOwner;

    public function findById(int $id): ?BotOwner;

    public function findByBaleChatId(string $chatId): ?BotOwner;

    public function createOrUpdateByPhone(string $phone, array $attributes = []): BotOwner;
}
