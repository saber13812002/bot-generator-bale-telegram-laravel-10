<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class StringHelper
{

    const command_template_sure = "/sure";
    const command_template_ayah = "ayah";
    const command_template_scan = '/scan';
    const command_template_hr = 'hr';
    const regex_sure_ayah = "/\/sure[0-9]+ayah[0-9]+/";
    const regex_sure = "/sure(.*?)ayah/";

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
    public static function getWeatherBotCommandsAsPostfixForMessages(): string
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
    public static function getHadithCommandsAsPostfixForMessages(): string
    {
        return self::getStringMessageDivider() . "
برای جستجو
در کل احادیث کتب شیعه دستور /search
و برای نمایش جستجوهای دیگران در کتب شیعی /history
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


 گزارش از ساعت 5 امروز تا یک نصف شب امشب (1 بامداد) سرعت شدید ترین باد در قم-فلکه ایران مرینوس در ساعات آینده
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
     * @param $academyOfIslamDataItem
     * @return string
     */
    public static function generateDetailHadithMessage($academyOfIslamDataItem): string
    {
//        dd($academyOfIslamDataItem);
//        dd(array_key_exists('book', $academyOfIslamDataItem));
//        dd(isset($academyOfIslamDataItem['book']));
        $book = $academyOfIslamDataItem['book'] ?? "";
        $number = isset($academyOfIslamDataItem['number']) ? $academyOfIslamDataItem["number"] : "";
        $part = isset($academyOfIslamDataItem['part']) ? $academyOfIslamDataItem["part"] : "";
        $chapter = isset($academyOfIslamDataItem['chapter']) ? $academyOfIslamDataItem["chapter"] : "";
//        $tags = $academyOfIslamDataItem["tags"][0];
        $arabic = isset($academyOfIslamDataItem['arabic']) ? $academyOfIslamDataItem["arabic"] : "";
//        $highlight = $academyOfIslamDataItem["highlight"];
        $english = isset($academyOfIslamDataItem['english']) ? $academyOfIslamDataItem["english"] : "";
//        $gradings = $academyOfIslamDataItem["gradings"][0];
//        $related = $academyOfIslamDataItem["related"][0];
//        $history = $academyOfIslamDataItem["history"][0];
        $_id = isset($academyOfIslamDataItem['_id']) ? $academyOfIslamDataItem["_id"] : "";

        return '
 شماره:' . $number . '
 کتاب:' . $book . '
 بخش:' . $part . '
 فصل:' . $chapter . '
 متن عربی:' . strip_tags($arabic) . (App::getLocale() != 'fa' ? '
 متن انگلیسی:' . substr($english, 0, 100) . '...' : "") . '
 شناسه:' . $_id . '
 ';
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


    /**
     * @param int $pageNumber
     * @return string
     */
    public static function get3digitNumber(int $pageNumber): string
    {
        return str_pad($pageNumber, 3, '0', STR_PAD_LEFT);
    }

    public static function isContainRegex(string $message): bool
    {
        return preg_match(StringHelper::regex_sure_ayah, $message);
    }

    public static function getSureAyeByRegex(string $message): array
    {
        if (preg_match(StringHelper::regex_sure, substr($message, 1, Str::length($message)), $match) == 1) {
            $sure = (integer)$match[1];
            if ($sure > 0) {
                $aya = (integer)substr($message, strpos($message, StringHelper::command_template_ayah) + Str::length(StringHelper::command_template_ayah));
                if ($aya > 0) {
                    return [$sure, $aya];
                }
            }
        }

        return [0, 0];
    }
}
