<?php

namespace App\Helpers;

use App\Interfaces\Services\ProService;

class ProHelper
{
    /**
     * بررسی دسترسی Pro
     */
    public static function checkProAccess(int $botUserId, int $botId, string $feature): bool
    {
        $proService = app(ProService::class);
        return $proService->canUseFeature($botUserId, $botId, $feature);
    }

    /**
     * الزام Pro - اگر Pro نباشد، false برمی‌گرداند
     */
    public static function requirePro(int $botUserId, int $botId, string $feature): bool
    {
        return self::checkProAccess($botUserId, $botId, $feature);
    }

    /**
     * پیام نیاز به Pro
     */
    public static function getProMessage(string $feature = ''): string
    {
        $message = trans('bot.pro_required');
        
        if ($feature) {
            $message .= "\n" . trans("bot.pro_feature_{$feature}");
        }
        
        $message .= "\n" . trans('bot.pro_buy_instruction');
        
        return $message;
    }
}
