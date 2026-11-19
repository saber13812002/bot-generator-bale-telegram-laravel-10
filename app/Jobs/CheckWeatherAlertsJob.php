<?php

namespace App\Jobs;

use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use App\Interfaces\Services\WeatherAlertService;
use App\Interfaces\Services\WeatherComparisonService;
use App\Models\BotUsers;
use App\Models\WeatherAlert;
use App\Models\WeatherHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckWeatherAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(
        WeatherAlertService $alertService,
        WeatherTomorrowApiRepository $weatherRepository,
        WeatherComparisonService $comparisonService
    ): void
    {
        Log::info('🔍 [WeatherAlert] Starting alert check job');

        $alerts = WeatherAlert::active()->get();

        Log::info('🔍 [WeatherAlert] Found alerts', ['count' => $alerts->count()]);

        foreach ($alerts as $alert) {
            try {
                $this->checkAlert($alert, $weatherRepository, $comparisonService, $alertService);
            } catch (\Exception $e) {
                Log::error('❌ [WeatherAlert] Error checking alert', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('✅ [WeatherAlert] Alert check job completed');
    }

    /**
     * چک کردن یک alert
     */
    private function checkAlert(
        WeatherAlert $alert,
        WeatherTomorrowApiRepository $weatherRepository,
        WeatherComparisonService $comparisonService,
        WeatherAlertService $alertService
    ): void
    {
        $user = $alert->botUser;
        if (!$user || !$user->hasLocation()) {
            return;
        }

        $location = $user->getLocation();
        
        // دریافت weather data فعلی
        try {
            $currentWeather = $weatherRepository->call($location['latitude'], $location['longitude']);
            
            // استخراج intervals
            if (!isset($currentWeather['data']['timelines'][0]['intervals'])) {
                Log::warning('⚠️ [WeatherAlert] No intervals in weather data', [
                    'alert_id' => $alert->id
                ]);
                return;
            }

            $intervals = $currentWeather['data']['timelines'][0]['intervals'];
            
            // اگر ساعت خاصی تعیین شده، فقط آن ساعت را چک کن
            if ($alert->time_hour !== null) {
                $targetHour = (int) $alert->time_hour;
                $now = now();
                $currentHour = (int) $now->format('H');
                
                // فقط اگر ساعت فعلی با ساعت alert مطابقت دارد
                if ($currentHour !== $targetHour) {
                    return;
                }
            }

            // دریافت داده‌های دیروز
            $previous = WeatherHistory::getYesterdayWeather(
                $location['latitude'],
                $location['longitude']
            );

            // مقایسه و trigger
            foreach ($intervals as $interval) {
                $shouldTrigger = $alertService->compareWeather(
                    $interval,
                    $previous?->weather_data,
                    $alert
                );

                if ($shouldTrigger) {
                    $this->triggerAlert($alert, $interval, $user, $location);
                    
                    // ذخیره weather data فعلی برای استفاده بعدی
                    WeatherHistory::saveWeatherData(
                        $location['latitude'],
                        $location['longitude'],
                        $interval
                    );
                    
                    break; // فقط یک بار trigger کن
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ [WeatherAlert] Error in checkAlert', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trigger کردن alert
     */
    private function triggerAlert(WeatherAlert $alert, array $weatherData, BotUsers $user, array $location): void
    {
        $alert->last_triggered_at = now();
        $alert->save();

        Log::info('🚨 [WeatherAlert] Alert triggered', [
            'alert_id' => $alert->id,
            'alert_type' => $alert->alert_type,
            'user_id' => $user->id
        ]);

        // ارسال پیام به کاربر
        $message = "🚨 " . trans('bot.alert_triggered') . "\n\n";
        $message .= trans("bot.alert_type_{$alert->alert_type}") . "\n";
        $message .= trans('bot.alert_location') . ": " . ($location['address'] ?? "{$location['latitude']}, {$location['longitude']}") . "\n";
        
        // اینجا باید از BotHelper استفاده شود اما نیاز به bot instance داریم
        // باید از طریق queue یا event انجام شود
    }
}
