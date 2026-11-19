<?php

namespace App\Services;

use App\Interfaces\Services\ProService;
use App\Interfaces\Services\WeatherAlertService;
use App\Interfaces\Services\WeatherComparisonService;
use App\Models\BotUsers;
use App\Models\WeatherAlert;
use App\Models\WeatherHistory;
use Illuminate\Support\Facades\Log;

class WeatherAlertServiceImpl implements WeatherAlertService
{
    private const MAX_ALERTS_FREE = 3;

    private ProService $proService;
    private WeatherComparisonService $weatherComparisonService;

    public function __construct(
        ProService $proService,
        WeatherComparisonService $weatherComparisonService
    ) {
        $this->proService = $proService;
        $this->weatherComparisonService = $weatherComparisonService;
    }

    /**
     * ایجاد alert
     */
    public function createAlert(int $botUserId, int $botId, array $alertData): array
    {
        $user = BotUsers::find($botUserId);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        // بررسی محدودیت alerts
        $isPro = $this->proService->isPro($botUserId, $botId);
        $activeAlertsCount = $user->getActiveAlertsCount($botId);

        if (!$isPro && $activeAlertsCount >= self::MAX_ALERTS_FREE) {
            return [
                'success' => false,
                'message' => trans('bot.alert_limit_reached', ['max' => self::MAX_ALERTS_FREE]),
                'is_pro_required' => true,
            ];
        }

        $alert = WeatherAlert::create([
            'bot_user_id' => $botUserId,
            'bot_id' => $botId,
            'alert_type' => $alertData['alert_type'],
            'comparison_type' => $alertData['comparison_type'],
            'threshold_value' => $alertData['threshold_value'],
            'time_hour' => $alertData['time_hour'] ?? null,
            'is_active' => true,
        ]);

        Log::info('🔔 [WeatherAlert] Alert created', [
            'alert_id' => $alert->id,
            'bot_user_id' => $botUserId,
            'bot_id' => $botId,
            'alert_type' => $alertData['alert_type'],
        ]);

        return [
            'success' => true,
            'alert_id' => $alert->id,
            'message' => trans('bot.alert_created'),
        ];
    }

    /**
     * چک کردن همه alerts
     */
    public function checkAlerts(): void
    {
        $alerts = WeatherAlert::active()->get();

        Log::info('🔍 [WeatherAlert] Checking alerts', [
            'count' => $alerts->count(),
        ]);

        foreach ($alerts as $alert) {
            try {
                $this->checkSingleAlert($alert);
            } catch (\Exception $e) {
                Log::error('❌ [WeatherAlert] Error checking alert', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * چک کردن یک alert
     */
    private function checkSingleAlert(WeatherAlert $alert): void
    {
        $user = $alert->botUser;
        if (!$user || !$user->hasLocation()) {
            return;
        }

        $location = $user->getLocation();
        
        // دریافت weather data فعلی (این باید از WeatherService گرفته شود)
        // برای حالا، این متد باید از WeatherService استفاده کند
        
        // دریافت داده‌های دیروز
        $previous = WeatherHistory::getYesterdayWeather(
            $location['latitude'],
            $location['longitude']
        );

        // این متد باید در Job پیاده‌سازی شود که weather data را دریافت می‌کند
    }

    /**
     * Trigger کردن alert
     */
    public function triggerAlert($alert, array $weatherData): void
    {
        $alert->last_triggered_at = now();
        $alert->save();

        Log::info('🚨 [WeatherAlert] Alert triggered', [
            'alert_id' => $alert->id,
            'alert_type' => $alert->alert_type,
        ]);

        // ارسال ایمیل و پیام (در Job پیاده‌سازی می‌شود)
    }

    /**
     * مقایسه آب و هوا
     */
    public function compareWeather(array $current, ?array $previous, $alert): bool
    {
        if (!$previous) {
            return false;
        }

        $comparison = $this->weatherComparisonService->compareWithYesterday($current, [
            'latitude' => $current['latitude'] ?? 0,
            'longitude' => $current['longitude'] ?? 0,
        ]);

        $change = null;

        switch ($alert->alert_type) {
            case 'temperature':
                $change = $comparison['temperature_change'];
                break;
            case 'precipitation':
                $change = $comparison['precipitation_change'];
                break;
            case 'wind':
                $change = $comparison['wind_change'];
                break;
            case 'snow':
                // برای برف، از precipitation استفاده می‌کنیم
                $change = $comparison['precipitation_change'];
                break;
        }

        if ($change === null) {
            return false;
        }

        $threshold = (float) $alert->threshold_value;

        switch ($alert->comparison_type) {
            case 'increase':
                return $change >= $threshold;
            case 'decrease':
                return $change <= -$threshold;
            case 'absolute':
                return abs($change) >= $threshold;
        }

        return false;
    }
}
