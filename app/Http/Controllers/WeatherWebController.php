<?php

namespace App\Http\Controllers;

use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use App\Models\BotUsers;
use App\Models\WeatherAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WeatherWebController extends Controller
{
    private WeatherTomorrowApiRepository $weatherRepository;

    public function __construct(WeatherTomorrowApiRepository $weatherRepository)
    {
        $this->weatherRepository = $weatherRepository;
    }

    /**
     * صفحه گزارش وب
     */
    public function report(Request $request, string $token)
    {
        // پیدا کردن کاربر بر اساس token
        $user = BotUsers::where('email_unsubscribe_token', $token)
            ->orWhere('email_verification_token', $token)
            ->first();

        if (!$user || !$user->hasLocation()) {
            abort(404, 'User not found or location not set');
        }

        $location = $user->getLocation();
        
        // دریافت weather data
        try {
            $weatherData = $this->weatherRepository->call(
                $location['latitude'],
                $location['longitude']
            );
        } catch (\Exception $e) {
            Log::error('🌤 [WeatherWeb] Error getting weather data', [
                'error' => $e->getMessage()
            ]);
            $weatherData = null;
        }

        // دریافت alerts
        $alerts = $user->weatherAlerts()
            ->where('is_active', true)
            ->get();

        return view('weather.report', [
            'user' => $user,
            'location' => $location,
            'weatherData' => $weatherData,
            'alerts' => $alerts,
        ]);
    }
}
