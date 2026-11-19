<?php

namespace App\Modules\AdminBots\Helpers;

use Illuminate\Support\Facades\Cache;

class AdminBotsStateHelper
{
    const STATE_IDLE = 'idle';
    const STATE_WAITING_ENDPOINT = 'waiting_endpoint';
    const STATE_WAITING_PLATFORM = 'waiting_platform';
    const STATE_WAITING_TOKEN = 'waiting_token';

    public static function getStateKey(string $chatId): string
    {
        return 'admin_bots_state_' . $chatId;
    }

    public static function getState(string $chatId): string
    {
        $data = Cache::get(self::getStateKey($chatId), []);

        return $data['state'] ?? self::STATE_IDLE;
    }

    public static function getStateData(string $chatId): array
    {
        $data = Cache::get(self::getStateKey($chatId), []);

        return $data['data'] ?? [];
    }

    public static function setState(string $chatId, string $state, array $data = []): void
    {
        Cache::put(self::getStateKey($chatId), [
            'state' => $state,
            'data' => $data,
        ], now()->addHours(2));
    }

    public static function clearState(string $chatId): void
    {
        Cache::forget(self::getStateKey($chatId));
    }
}
