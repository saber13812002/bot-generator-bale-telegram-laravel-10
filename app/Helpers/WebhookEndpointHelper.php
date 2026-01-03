<?php

namespace App\Helpers;

use App\Models\Bot;

class WebhookEndpointHelper
{
    /**
     * لیست تمام webhook endpoint های موجود در سیستم
     * 
     * @return array
     */
    public static function getAvailableEndpoints(): array
    {
        $baseUrl = env('APP_URL', 'https://your-domain.com');
        
        return [
            [
                'id' => 'webhook-personnel-registration',
                'name' => 'ثبت‌نام پرسنل',
                'route' => '/api/webhook-personnel-registration',
                'description' => 'ربات ثبت‌نام پرسنل',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => true,
            ],
            [
                'id' => 'webhook-personnel-admin',
                'name' => 'ادمین ثبت‌نام پرسنل',
                'route' => '/api/webhook-personnel-admin',
                'description' => 'ربات ادمین برای مشاهده لیست ثبت‌نام‌های پرسنل',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-mission-bot',
                'name' => 'ربات ماموریت',
                'route' => '/api/webhook-mission-bot',
                'description' => 'ربات مدیریت ماموریت‌ها',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-mission-media',
                'name' => 'ربات مدیا ماموریت',
                'route' => '/api/webhook-mission-media',
                'description' => 'ربات مدیریت و آپلود مدیاهای آموزشی ماموریت‌ها',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-task-approval',
                'name' => 'تایید وظایف',
                'route' => '/api/webhook-task-approval',
                'description' => 'ربات تایید و رد وظایف',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-weather',
                'name' => 'آب و هوا',
                'route' => '/api/webhook-weather',
                'description' => 'ربات اطلاع از آب و هوا',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-quran-word',
                'name' => 'کامل قرآن مرور ختم و حفظ شماره 7',
                'route' => '/api/webhook-quran-word',
                'description' => 'ربات کامل قرآن مرور ختم و حفظ شماره 7',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => true,
                'supports_multiple_languages' => true, // پشتیبانی از 18 زبان
            ],
            [
                'id' => 'webhook-quran-ayat',
                'name' => 'جستجوی آیات قرآن',
                'route' => '/api/webhook-quran-ayat',
                'description' => 'ربات جستجوی آیات قرآن',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-hadith',
                'name' => 'حدیث',
                'route' => '/api/webhook-hadith',
                'description' => 'ربات جستجوی احادیث',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-nahj',
                'name' => 'نهج البلاغه',
                'route' => '/api/webhook-nahj',
                'description' => 'ربات جستجوی نهج البلاغه',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-blog',
                'name' => 'وبلاگ',
                'route' => '/api/webhook-blog',
                'description' => 'ربات مدیریت وبلاگ',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-rss',
                'name' => 'RSS Feed',
                'route' => '/api/webhook-rss',
                'description' => 'ربات RSS Feed',
                'requires_bot_mother_id' => false,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-bot-children',
                'name' => 'ربات‌های فرزند',
                'route' => '/api/webhook-bot-children',
                'description' => 'ربات مدیریت ربات‌های فرزند',
                'requires_bot_mother_id' => false,
                'requires_token' => false,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-presenter-bot',
                'name' => 'ربات پرزنتر',
                'route' => '/api/webhook-presenter-bot',
                'description' => 'ربات ارائه دهنده - ارسال متن‌های آماده به ترتیب',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
            [
                'id' => 'webhook-psychology-test',
                'name' => 'تست روانشناسی',
                'route' => '/api/webhook-psychology-test',
                'description' => 'ربات تست روانشناسی - سوالات 5 گزینه‌ای با دسته‌بندی و محاسبه امتیازات',
                'requires_bot_mother_id' => true,
                'requires_token' => true,
                'requires_language' => false,
            ],
        ];
    }

    /**
     * ساخت webhook URL برای یک endpoint خاص
     * 
     * @param string $endpointId
     * @param Bot $botItem
     * @param string $type
     * @param string $language
     * @param int $botMotherId
     * @return string
     */
    public static function createWebhookUrl(string $endpointId, Bot $botItem, string $type, string $language = 'fa', int $botMotherId = 1): string
    {
        $endpoints = self::getAvailableEndpoints();
        $endpoint = collect($endpoints)->firstWhere('id', $endpointId);
        
        if (!$endpoint) {
            throw new \Exception("Endpoint not found: {$endpointId}");
        }

        $baseUrl = env('APP_URL', 'https://your-domain.com');
        $url = $baseUrl . $endpoint['route'];
        
        $params = [];
        
        // اضافه کردن پارامترهای مورد نیاز
        if ($endpoint['requires_token']) {
            $token = $type == 'bale' ? $botItem->bale_bot_token : $botItem->telegram_bot_token;
            $params['token'] = $token;
        }
        
        if ($endpoint['requires_bot_mother_id']) {
            $params['bot_mother_id'] = $botMotherId;
        }
        
        if ($endpoint['requires_language']) {
            $params['language'] = $language;
        }
        
        // پارامتر origin همیشه لازم است
        $params['origin'] = $type;
        
        // اضافه کردن bot_id به URL (همیشه لازم است)
        // اگر bot_id هنوز وجود ندارد (ربات جدید)، باید ابتدا save شود
        if (!$botItem->id) {
            $botItem->save();
        }
        $params['bot_id'] = $botItem->id;
        
        // برای webhook-bot-children پارامترهای خاص لازم است
        if ($endpointId === 'webhook-bot-children') {
            $params['bot_user_name'] = $botItem->bale_bot_name ?? $botItem->telegram_bot_name;
            $params['bot_token'] = $type == 'bale' ? $botItem->bale_bot_token : $botItem->telegram_bot_token;
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }

    /**
     * دریافت endpoint بر اساس ID
     * 
     * @param string $endpointId
     * @return array|null
     */
    public static function getEndpointById(string $endpointId): ?array
    {
        $endpoints = self::getAvailableEndpoints();
        return collect($endpoints)->firstWhere('id', $endpointId);
    }

    /**
     * ساخت پیام لیست endpoint ها برای نمایش به کاربر
     * 
     * @return string
     */
    public static function getEndpointsListMessage(): string
    {
        $endpoints = self::getAvailableEndpoints();
        $message = "📋 لیست endpoint های موجود:\n\n";
        
        foreach ($endpoints as $index => $endpoint) {
            $number = $index + 1;
            $message .= "{$number}. {$endpoint['name']}\n";
            $message .= "   📝 {$endpoint['description']}\n";
            $message .= "   🔗 {$endpoint['route']}\n\n";
        }
        
        $message .= "برای انتخاب endpoint، شماره آن را ارسال کنید.";
        
        return $message;
    }
}

