<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use App\Interfaces\Services\WeatherTomorrowApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class WeatherTomorrowApiServiceImpl implements WeatherTomorrowApiService
{

    private WeatherTomorrowApiRepository $weatherTomorrowApiRepository;

    public function __construct(WeatherTomorrowApiRepository $weatherTomorrowApiRepository)
    {
        $this->weatherTomorrowApiRepository = $weatherTomorrowApiRepository;
    }

    /**
     */
    public function getMessage(string $userText, float $latitude, float $longitude, $isBot = true): string
    {
        if ($isBot) {
            $message = $this->getMessageFromTomorrowApi($userText, $latitude, $longitude);
            if ($message == "")
                return "هیچ گزارش سرعت بالای حد تعیین شده نداشتیم";
            else
                return $message;
        }
        return "";
    }


    /**
     * @param string $botText
     * @param float $latitude
     * @param float $longitude
     * @return string
     * @throws Exception
     */
    public function getMessageFromTomorrowApi(string $botText, float $latitude, float $longitude): string
    {
        try {
            $weather_data = $this->weatherTomorrowApiRepository->call($latitude, $longitude);
            
            // لاگ برای بررسی ساختار پاسخ API
            Log::info('🌤 [Weather] API Response Structure', [
                'has_data' => isset($weather_data['data']),
                'has_timelines' => isset($weather_data['data']['timelines']),
                'timelines_count' => isset($weather_data['data']['timelines']) ? count($weather_data['data']['timelines']) : 0,
                'has_intervals' => isset($weather_data['data']['timelines'][0]['intervals']),
                'intervals_count' => isset($weather_data['data']['timelines'][0]['intervals']) ? count($weather_data['data']['timelines'][0]['intervals']) : 0,
                'response_keys' => array_keys($weather_data ?? []),
            ]);
            
            // بررسی وجود داده
            if (!isset($weather_data['data']['timelines'][0]['intervals'])) {
                Log::error('🌤 [Weather] Missing intervals in API response', [
                    'weather_data_structure' => json_encode($weather_data, JSON_PRETTY_PRINT)
                ]);
                return "خطا در دریافت داده‌های هواشناسی. لطفاً دوباره تلاش کنید.";
            }
            
        } catch (Exception $e) {
            Log::error('🌤 [Weather] API Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return StringHelper::findString($e->getMessage(), "Too Many Calls") 
                ? substr($e->getMessage(), -180) 
                : " خطای ناشناخته ". substr($e->getMessage(), -180) ."
";
        }
        return self::generateMessageByTomorrowData($botText, $weather_data['data']['timelines'][0]['intervals']);
    }

    /**
     * @param $botText
     * @param mixed $weatherData
     * @return string
     */
    private static function generateMessageByTomorrowData($botText, mixed $weatherData): string
    {
        $windSpeedLimit = 20;
        if (intval($botText)) {
            $windSpeedLimit = min(intval($botText), $windSpeedLimit);
        }

        $hoursBitCount = 1;
        $raiseLimitCount = 0;
        $message = "";
        
        // لاگ برای بررسی داده‌های دریافتی
        Log::info('🌤 [Weather] Processing weather data', [
            'wind_speed_limit' => $windSpeedLimit,
            'data_count' => is_array($weatherData) ? count($weatherData) : 0,
            'bot_text' => $botText
        ]);

        if (!is_array($weatherData) || empty($weatherData)) {
            Log::warning('🌤 [Weather] Empty or invalid weather data', [
                'weather_data_type' => gettype($weatherData),
                'weather_data' => $weatherData
            ]);
            return "هیچ داده هواشناسی دریافت نشد.";
        }

        foreach ($weatherData as $weatherDataItem) {
            $originalStartDateTime = $weatherDataItem["startTime"];
            $datetime = new Carbon($originalStartDateTime);

            $timezone = 'Asia/Tehran';
            $today5evening = Carbon::parse('today 5pm', $timezone);
            $tomorrow1am = Carbon::parse('tomorrow 1am', $timezone);
            $now = Carbon::now($timezone);

//            dd($today5evening, $tomorrow1am, $now);
            if ($datetime->gte($now) && $datetime->gte($today5evening) && $datetime->lte($tomorrow1am)) {

                $hoursBitCount++;
                if ($hoursBitCount > 15) {
                    return $message;
                }

//                $weather_description = ($weatherDataItem["values"]["rainIntensity"]);
//                BotHelper::sendMessageToSuperAdmin($weather_description, 'telegram');

                $windSpeed = $weatherDataItem["values"]['windGust'];

                if ($windSpeed > $windSpeedLimit) {
                    $raiseLimitCount++;
                    $message .= StringHelper::addLineToMessageForSecondItemToLast($raiseLimitCount);
                    $message .= StringHelper::generateDetailWeatherMessage($weatherDataItem);
                }
            }
        }
        if ($raiseLimitCount > 0) {
            $message .= self::addPostfixMessage($raiseLimitCount, $windSpeedLimit);
            return $message;
        }
        return "";
    }


    /**
     * @param int $raiseLimitCount
     * @param int $windSpeedLimit
     * @return string
     */
    public static function addPostfixMessage(int $raiseLimitCount, int $windSpeedLimit): string
    {
        return StringHelper::getTomorrowApiPostfixReport($raiseLimitCount, $windSpeedLimit);
    }

    private static function convertWeatherTomorrowDescriptionToPersian(mixed $rainIntensity): string
    {
        $translate = ["آسمان صاف☀️",
            "کمی ابری🌤",
            "ابرهای پراکنده⛅️",
            "ابرهای شکسته🌤",
            "باران نرم⛈",
            "باران🌧",
            "رعد و برق⚡️",
            "برف❄️",
            "مه🌫"];

        if ($rainIntensity < 5) {
            return $translate[0];
        } elseif ($rainIntensity < 10) {
            return $translate[1];
        } elseif ($rainIntensity < 20) {
            return $translate[2];
        }
        return "وضعیت مشخص نیست";
    }


}
