<?php

namespace App\Helpers;

/**
 * Helper برای ساختار mock کردن update های Bale/Telegram
 * بر اساس ساختار واقعی از Insomnia
 */
class WebhookMockHelper
{
    /**
     * ساختار update برای پیام متنی Bale
     * بر اساس تست‌های واقعی Insomnia
     *
     * @param string $text متن پیام
     * @param int $chatId شناسه چت (پیش‌فرض: 485750575 از Insomnia)
     * @param int $userId شناسه کاربر (پیش‌فرض: 485750575 از Insomnia)
     * @return array ساختار update
     */
    public static function mockBaleUpdate(string $text, int $chatId = 485750575, int $userId = 485750575): array
    {
        return [
            'update_id' => rand(1, 1000),
            'message' => [
                'message_id' => rand(-1000000000, -1000000),
                'from' => [
                    'id' => $userId,
                    'first_name' => 'صابر طباطبایی یزدی',
                    'username' => 'sabertaba',
                    'is_bot' => false
                ],
                'date' => time(),
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                    'username' => 'sabertaba',
                    'first_name' => 'صابر طباطبایی یزدی'
                ],
                'text' => $text
            ]
        ];
    }

    /**
     * ساختار update برای پیام متنی Telegram
     *
     * @param string $text متن پیام
     * @param int $chatId شناسه چت
     * @param int $userId شناسه کاربر
     * @return array ساختار update
     */
    public static function mockTelegramUpdate(string $text, int $chatId = 485750575, int $userId = 485750575): array
    {
        return self::mockBaleUpdate($text, $chatId, $userId);
    }

    /**
     * ساختار callback query برای شبیه‌سازی کلیک روی دکمه
     * بر اساس ساختار واقعی Bale/Telegram
     *
     * @param string $callbackData داده callback (مثلاً "/1" یا "/start")
     * @param int $chatId شناسه چت
     * @param int $messageId شناسه پیام
     * @param int $userId شناسه کاربر
     * @return array ساختار update با callback_query
     */
    public static function mockCallbackQuery(
        string $callbackData,
        int $chatId = 485750575,
        int $messageId = -1515335176,
        int $userId = 485750575
    ): array {
        return [
            'update_id' => rand(1, 1000),
            'callback_query' => [
                'id' => uniqid('cq_', true),
                'from' => [
                    'id' => $userId,
                    'first_name' => 'صابر طباطبایی یزدی',
                    'username' => 'sabertaba',
                    'is_bot' => false
                ],
                'message' => [
                    'message_id' => $messageId,
                    'chat' => [
                        'id' => $chatId,
                        'type' => 'private'
                    ]
                ],
                'data' => $callbackData
            ]
        ];
    }

    /**
     * شبیه‌سازی کلیک روی دکمه inline keyboard
     *
     * @param string $callbackData داده callback
     * @return array ساختار callback query
     */
    public static function mockInlineKeyboardClick(string $callbackData): array
    {
        return self::mockCallbackQuery($callbackData);
    }

    /**
     * ساختار پیام با دکمه inline keyboard
     *
     * @param string $buttonText متن دکمه
     * @param string $callbackData داده callback
     * @return array ساختار reply_markup
     */
    public static function mockButtonMessage(string $buttonText, string $callbackData): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => $buttonText,
                        'callback_data' => $callbackData
                    ]
                ]
            ]
        ];
    }

    /**
     * ساختار update برای تست با token
     * برای تست ربات مادر
     *
     * @param string $token توکن ربات
     * @return array ساختار update
     */
    public static function mockTokenUpdate(string $token): array
    {
        return self::mockBaleUpdate($token);
    }

    /**
     * ساختار update برای دستور /start
     *
     * @param int $chatId شناسه چت
     * @return array ساختار update
     */
    public static function mockStartCommand(int $chatId = 485750575): array
    {
        return self::mockBaleUpdate('/start', $chatId);
    }

    /**
     * ساختار update برای دستور /new_bot
     *
     * @param int $chatId شناسه چت
     * @return array ساختار update
     */
    public static function mockNewBotCommand(int $chatId = 485750575): array
    {
        return self::mockBaleUpdate('/new_bot', $chatId);
    }

    /**
     * ساختار update برای جستجو در قرآن
     *
     * @param string $searchPhrase عبارت جستجو
     * @return array ساختار update
     */
    public static function mockQuranSearch(string $searchPhrase): array
    {
        return self::mockBaleUpdate($searchPhrase);
    }

    /**
     * ساختار update برای دستور هواشناسی
     *
     * @param string $command دستور (مثلاً "/current" یا "/forecasting")
     * @return array ساختار update
     */
    public static function mockWeatherCommand(string $command = '/current'): array
    {
        return self::mockBaleUpdate($command);
    }

    /**
     * ساختار update برای جستجو در حدیث
     *
     * @param string $searchPhrase عبارت جستجو
     * @return array ساختار update
     */
    public static function mockHadithSearch(string $searchPhrase): array
    {
        return self::mockBaleUpdate('/search ' . $searchPhrase);
    }

    /**
     * ساختار کامل update با تمام فیلدهای اختیاری
     * برای تست‌های پیشرفته
     *
     * @param array $options گزینه‌های سفارشی
     * @return array ساختار update کامل
     */
    public static function mockFullUpdate(array $options = []): array
    {
        $defaults = [
            'text' => 'test',
            'chat_id' => 485750575,
            'user_id' => 485750575,
            'message_id' => rand(-1000000000, -1000000),
            'forward_from_chat' => null,
            'forward_from' => null
        ];

        $options = array_merge($defaults, $options);

        $update = [
            'update_id' => rand(1, 1000),
            'message' => [
                'message_id' => $options['message_id'],
                'from' => [
                    'id' => $options['user_id'],
                    'first_name' => 'صابر طباطبایی یزدی',
                    'username' => 'sabertaba',
                    'is_bot' => false
                ],
                'date' => time(),
                'chat' => [
                    'id' => $options['chat_id'],
                    'type' => 'private',
                    'username' => 'sabertaba',
                    'first_name' => 'صابر طباطبایی یزدی'
                ],
                'text' => $options['text']
            ]
        ];

        if ($options['forward_from_chat']) {
            $update['message']['forward_from_chat'] = $options['forward_from_chat'];
        }

        if ($options['forward_from']) {
            $update['message']['forward_from'] = $options['forward_from'];
        }

        return $update;
    }
}


