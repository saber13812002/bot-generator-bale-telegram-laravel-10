<?php

namespace App\Helpers;

use Carbon\Carbon;

class StringHelper
{


    /**
     * @param $string
     * @param $subString
     * @return bool
     */
    public static function findString($string, $subString): bool
    {
        if (str_contains($string, $subString)) {
            return true;
        }
        return false;
    }


    /**
     * @return string
     */
    public static function getCommandsAsPostfixForMessages(): string
    {
        return self::getStringMessageDivider() . "
برای استعلام
وضعیت هواشناسی فعلی دستور /current
و برای پیش بینی باد در 16 ساعت آینده /forecasting
را کلیک یا ارسال کنید.";
    }


    /**
     * @return string
     */
    public static function getStringMessageDivider(): string
    {
        return '
=====================';
    }


    /**
     * @param int $raiseLimitCount
     * @param int $windSpeedLimit
     * @return string
     */
    public static function getTomorrowApiPostfixReport(int $raiseLimitCount, int $windSpeedLimit): string
    {
        return "


 گزارش از ساعت 5 امروز تا یک نصف شب امشب (1 بامداد) سرعت باد در قم-فلکه ایران مرینوس در ساعات آینده
 تعداد " . $raiseLimitCount . " بار سرعت بالای " . $windSpeedLimit . " کیلومتر بر ساعت
 گزارش شده است";
    }


    /**
     * @param $weatherDataItem
     * @return string
     */
    public static function generateDetailWeatherMessage($weatherDataItem): string
    {
        $originalStartDateTime = $weatherDataItem["startTime"];
        $datetime = new Carbon($originalStartDateTime);
        $jalaliStartDateTime = verta($datetime);
        $windSpeed = $weatherDataItem["values"]['windSpeed'];
        $visibility = $weatherDataItem["values"]["visibility"];
        $clouds = $weatherDataItem["values"]["cloudCover"];
        $temp = $weatherDataItem["values"]["temperature"];
        $feels_like = $weatherDataItem["values"]["temperatureApparent"];
        $humidity = $weatherDataItem["values"]["humidity"];
        $pressure = $weatherDataItem["values"]["pressureSeaLevel"];

        return '
وضعیت قرمز 😥 باد 🌬 در ساعت :
 تاریخ میلادی:' . $originalStartDateTime . '
 تاریخ شمسی:' . $jalaliStartDateTime . '
 دید و برد چشم:' . $visibility . '
 تعداد ابرها:' . $clouds . '
 دمای هوا:' . $temp . '
 دمای هوا که احساس میشه:' . $feels_like . '
 رطوبت:' . $humidity . '
 فشار هوا:' . $pressure . '
 وضعیت باد 🌬 :.' . '
 💨 سرعت  :' . $windSpeed . " km/s کیلومتر بر ساعت- " . ($windSpeed > 13 ? " 🌪 " : " ⚡ ") . '
🧭 زاویه  : ' . $weatherDataItem["values"]['windDirection'] . '
 🌪 وزش شدید  :' . $weatherDataItem["values"]['windGust'];
    }


    /**
     * @param int $raiseLimitCount
     * @return string
     */
    public static function addLineToMessageForSecondItemToLast(int $raiseLimitCount): string
    {
        if ($raiseLimitCount > 1) {
            return StringHelper::getStringMessageDivider();
        }
        return "";
    }


    public static function mapStringOpenWeatherApi($desiredKey): int|string
    {
        $translate = ["clear sky" => "آسمان صاف☀️",
            "few clouds" => "کمی ابری🌤",
            "scattered clouds" => "ابرهای پراکنده⛅️",
            "broken clouds" => "ابرهای شکسته🌤",
            "shower rain" => "باران نرم⛈",
            "rain" => "باران🌧",
            "thunderstorm" => "رعد و برق⚡️",
            "snow" => "برف❄️",
            "mist" => "مه🌫"];

        foreach ($translate as $key => $value) {
            if ($key == $desiredKey) {
                return $value; // or return true;
            }
        }
        return -1;
    }


    public static function insertTextForAdmin($bot, $type): string
    {
        return "
چت آی دی:" . $bot->ChatID() . "
" . "
نام:" . $bot->FirstName() . "
" . "
نام خ:" . $bot->LastName() . "
" . "
مرجع:" . $type . "
" . "
محیط:" . env('APP_ENV') . "
";
    }
}
