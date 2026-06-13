<?php

namespace App\Modules\BotOwner\Repositories;

use App\Modules\BotOwner\Contracts\BotOwnerRepositoryInterface;
use App\Modules\BotOwner\Models\BotOwner;

class BotOwnerRepository implements BotOwnerRepositoryInterface
{
    public function findByPhone(string $phone): ?BotOwner
    {
        return BotOwner::where('phone', $phone)->first();
    }

    public function findById(int $id): ?BotOwner
    {
        return BotOwner::find($id);
    }

    public function findByBaleChatId(string $chatId): ?BotOwner
    {
        return BotOwner::where('bale_chat_id', $chatId)->first();
    }

    public function createOrUpdateByPhone(string $phone, array $attributes = []): BotOwner
    {
        return BotOwner::updateOrCreate(
            ['phone' => $phone],
            $attributes
        );
    }
}
