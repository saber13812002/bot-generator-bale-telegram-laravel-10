<?php

namespace App\Helpers;

use App\Models\Bot;
use App\Models\WebhookEndpoint;

class WebhookEndpointHelper
{
    /**
     * لیست تمام webhook endpoint های موجود در سیستم
     * خواندن از جدول webhook_endpoints
     * 
     * @return array
     */
    public static function getAvailableEndpoints(): array
    {
        try {
            // خواندن از جدول
            $endpoints = WebhookEndpoint::where('is_active', true)
                ->orderBy('name')
                ->get();
            
            if ($endpoints->isEmpty()) {
                // اگر جدول خالی است، آرایه خالی برگردان
                return [];
            }
            
            // تبدیل به فرمت مورد نیاز
            return $endpoints->map(function ($endpoint) {
                return [
                    'id' => $endpoint->endpoint_id,
                    'name' => $endpoint->name,
                    'route' => $endpoint->route,
                    'description' => $endpoint->description,
                    'requires_bot_mother_id' => $endpoint->requires_bot_mother_id,
                    'requires_token' => $endpoint->requires_token,
                    'requires_language' => $endpoint->requires_language,
                    'supports_multiple_languages' => $endpoint->supports_multiple_languages,
                ];
            })->toArray();
        } catch (\Exception $e) {
            // در صورت خطا (مثلاً جدول وجود ندارد)، آرایه خالی برگردان
            \Log::warning('Error reading webhook endpoints from database: ' . $e->getMessage());
            return [];
        }
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
    public static function createWebhookUrl(string $endpointId, Bot $botItem, string $type, string $language = null, int $botMotherId = 1): string
    {
        $endpoints = self::getAvailableEndpoints();
        $endpoint = collect($endpoints)->firstWhere('id', $endpointId);
        
        if (!$endpoint) {
            throw new \Exception("Endpoint not found: {$endpointId}");
        }

        $baseUrl = env('APP_URL', 'https://your-domain.com');
        // اطمینان از وجود slash در انتهای baseUrl و ابتدای route
        $baseUrl = rtrim($baseUrl, '/');
        $route = ltrim($endpoint['route'], '/');
        $url = $baseUrl . '/' . $route;
        
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
            // اگر language داده نشد، از language_code ربات استفاده کن
            if (!$language && $botItem->language_code) {
                $language = $botItem->language_code;
            }
            // اگر هنوز language نداریم، از پیش‌فرض fa استفاده کن
            if (!$language) {
                $language = 'fa';
            }
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

