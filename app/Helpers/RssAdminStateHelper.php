<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class RssAdminStateHelper
{
    private const CACHE_PREFIX = 'rss_admin_state_';

    private const CACHE_TTL = 3600;

    public static function setState(mixed $chatId, string $state, array $data = []): void
    {
        Cache::put(self::CACHE_PREFIX . $chatId, [
            'state' => $state,
            'data' => $data,
        ], self::CACHE_TTL);
    }

    public static function getState(mixed $chatId): ?array
    {
        return Cache::get(self::CACHE_PREFIX . $chatId);
    }

    public static function getCurrentState(mixed $chatId): ?string
    {
        $state = self::getState($chatId);

        return $state['state'] ?? null;
    }

    public static function getData(mixed $chatId): array
    {
        $state = self::getState($chatId);

        return $state['data'] ?? [];
    }

    public static function clearState(mixed $chatId): void
    {
        Cache::forget(self::CACHE_PREFIX . $chatId);
    }
}
