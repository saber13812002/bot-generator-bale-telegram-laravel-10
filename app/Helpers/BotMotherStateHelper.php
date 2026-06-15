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
    const STATE_WAITING_PRESENTER_CONTENT = 'waiting_presenter_content';
    const STATE_WAITING_RATING_CONTENT = 'waiting_rating_content';
    const STATE_WAITING_PSYCHOLOGY_QUESTIONS = 'waiting_psychology_questions';
    const STATE_WAITING_CATEGORY_DESCRIPTIONS = 'waiting_category_descriptions';
    const STATE_WAITING_BROADCAST_LANGUAGE = 'waiting_broadcast_language';
    const STATE_WAITING_BROADCAST_MESSAGE = 'waiting_broadcast_message';
    const STATE_WAITING_QURAN_BOTS_LANGUAGE = 'waiting_quran_bots_language';
    const STATE_WAITING_QURAN_BOTS_SOURCE = 'waiting_quran_bots_source';

    // Content submission bot wizard
    const STATE_WAITING_CONTENT_BOT_CHANNEL_CONFIRM = 'waiting_content_bot_channel_confirm';
    const STATE_WAITING_CONTENT_BOT_CHANNEL_FORWARD = 'waiting_content_bot_channel_forward';
    const STATE_WAITING_CONTENT_BOT_NEED_APPROVAL = 'waiting_content_bot_need_approval';
    const STATE_WAITING_CONTENT_BOT_GROUP_FORWARD = 'waiting_content_bot_group_forward';
    const STATE_WAITING_CONTENT_BOT_REQUIRED_APPROVALS = 'waiting_content_bot_required_approvals';

    // Admin daily channel (verse/hadith/nahj/sharabe_beheshti) wizard
    const STATE_WAITING_DAILY_CHANNEL_CONTENT_TYPE = 'waiting_daily_channel_content_type';
    const STATE_WAITING_DAILY_CHANNEL_EDIT_OR_NEW = 'waiting_daily_channel_edit_or_new';
    const STATE_WAITING_DAILY_CHANNEL_POSTS_PER_DAY = 'waiting_daily_channel_posts_per_day';
    const STATE_WAITING_DAILY_CHANNEL_BALE_FORWARD = 'waiting_daily_channel_bale_forward';
    const STATE_WAITING_DAILY_CHANNEL_TELEGRAM_FORWARD = 'waiting_daily_channel_telegram_forward';
    const STATE_WAITING_DAILY_CHANNEL_EITAA_ID = 'waiting_daily_channel_eitaa_id';

    // Admin channel with media queue wizard
    const STATE_MEDIA_QUEUE_SELECT_OR_CREATE = 'media_queue_select_or_create';
    const STATE_MEDIA_QUEUE_NEW_NAME = 'media_queue_new_name';
    const STATE_MEDIA_QUEUE_ADD_ITEMS = 'media_queue_add_items';
    const STATE_MEDIA_QUEUE_BALE = 'media_queue_bale';
    const STATE_MEDIA_QUEUE_TELEGRAM = 'media_queue_telegram';
    const STATE_MEDIA_QUEUE_EITAA = 'media_queue_eitaa';

    // Book library — link reader bot after main bot token
    const STATE_WAITING_LIBRARY_READER_TOKEN = 'waiting_library_reader_token';
}

