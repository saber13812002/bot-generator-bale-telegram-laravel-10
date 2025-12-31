<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class BotMotherStateHelper
{
    const CACHE_PREFIX = 'bot_mother_state_';
    const CACHE_TTL = 3600; // 1 hour

    /**
     * ذخیره state برای یک کاربر
     * 
     * @param mixed $chatId
     * @param string $state
     * @param array $data
     * @return void
     */
    public static function setState(mixed $chatId, string $state, array $data = []): void
    {
        $key = self::CACHE_PREFIX . $chatId;
        Cache::put($key, [
            'state' => $state,
            'data' => $data,
        ], self::CACHE_TTL);
    }

    /**
     * دریافت state کاربر
     * 
     * @param mixed $chatId
     * @return array|null
     */
    public static function getState(mixed $chatId): ?array
    {
        $key = self::CACHE_PREFIX . $chatId;
        return Cache::get($key);
    }

    /**
     * دریافت state فعلی کاربر
     * 
     * @param mixed $chatId
     * @return string|null
     */
    public static function getCurrentState(mixed $chatId): ?string
    {
        $state = self::getState($chatId);
        return $state['state'] ?? null;
    }

    /**
     * دریافت data کاربر
     * 
     * @param mixed $chatId
     * @return array
     */
    public static function getData(mixed $chatId): array
    {
        $state = self::getState($chatId);
        return $state['data'] ?? [];
    }

    /**
     * اضافه کردن data به state
     * 
     * @param mixed $chatId
     * @param array $data
     * @return void
     */
    public static function addData(mixed $chatId, array $data): void
    {
        $currentState = self::getState($chatId);
        if ($currentState) {
            $currentState['data'] = array_merge($currentState['data'] ?? [], $data);
            self::setState($chatId, $currentState['state'], $currentState['data']);
        }
    }

    /**
     * پاک کردن state کاربر
     * 
     * @param mixed $chatId
     * @return void
     */
    public static function clearState(mixed $chatId): void
    {
        $key = self::CACHE_PREFIX . $chatId;
        Cache::forget($key);
    }

    /**
     * States
     */
    const STATE_IDLE = 'idle';
    const STATE_WAITING_ENDPOINT_SELECTION = 'waiting_endpoint_selection';
    const STATE_WAITING_TOKEN = 'waiting_token';
    const STATE_WAITING_TYPE = 'waiting_type';
    const STATE_WAITING_LANGUAGE = 'waiting_language';
}

