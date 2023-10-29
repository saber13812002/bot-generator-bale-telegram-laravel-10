<?php

namespace App\Helpers;

use App\Models\BotHadithItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
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
        // TODO: random implementation
        return self::getStringMessageDivider() . "
برای جستجو
در کل احادیث کتب شیعه دستور /search
و برای نمایش جستجوهای دیگران در کتب شیعی /history
و برای ارسال یک حدیث تصادفی /random
را کلیک یا ارسال کنید.";
    }


    /**
     * @return string
     */
    public static function getNahjCommandsAsPostfixForMessages(): string
    {
        $botType = Config::get('config.bot.type', 'bale');
        // TODO: random implementation
        return self::getStringMessageDivider() . "
برای جستجو
در کل متن نهج البلاغه دستور / search
و برای نمایش جستجوهای دیگران / history
و برای ارسال یک حکمت یا خطبه یا نامه تصادفی "." ". BotHelper::generateLink("/random", $botType)."
 و برای نمایش فهرست.    " . BotHelper::generateLink("/fehrest", $botType) .
            "
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
        list($book, $number, $part, $chapter, $arabic, $english, $id2) = self::getItemFields($academyOfIslamDataItem);

        $botType = Config::get('config.bot.type', 'bale'); //bottype

        $isLong = Str::length(strip_tags($arabic)) > 1000;

        if ($id2) {
            self::saveLongTextToDB($academyOfIslamDataItem);
        }

        $cmd = "/_id" . $id2;

        return self::getStringHadith($book, $number, $part, $chapter, $arabic, $english, $id2, $isLong) . '
 ' . '
 ' . ($isLong ? BotHelper::generateTextLink($cmd, $cmd, $botType) : '');
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


    private static function saveLongTextToDB(mixed $academyOfIslamDataItem)
    {
        list($book, $number, $part, $chapter, $arabic, $english, $id2) = self::getItemFields($academyOfIslamDataItem);
//        dd($id2);
        return self::firstOrCreate($id2, $book, $number, $part, $chapter, $arabic, $english);
    }

    /**
     * @param mixed $academyOfIslamDataItem
     * @return array|string[]
     */
    public static function getItemFields(mixed $academyOfIslamDataItem): array
    {
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
        $id2 = isset($academyOfIslamDataItem['_id']) ? $academyOfIslamDataItem["_id"] : "";
        return array($book, $number, $part, $chapter, $arabic, $english, $id2);
    }

    /**
     * @param string $id2
     * @param string $book
     * @param string $number
     * @param string $part
     * @param string $chapter
     * @param string $arabic
     * @param string $english
     * @return void
     */
    public static function firstOrCreate(string $id2, string $book, string $number, string $part, string $chapter, string $arabic, string $english)
    {
        $botHadithItem = BotHadithItem::firstOrCreate([
            'id2' => $id2
        ], [
//            'id2' => $id2,
            'book' => $book,
            'number' => $number,
            'part' => $part,
            'chapter' => $chapter,
            'arabic' => $arabic,
            'english' => $english
//            'arabic' => $arabic,
//            'arabic' => $arabic,
//            'arabic' => $arabic
        ]);
//        $botHadithItem->save();
        return $botHadithItem;
    }

    public static function getStringHadith($book, $number, $part, $chapter, $arabic, $english, $id2, $isLong): string
    {
        return '
' . trans("hadith.result.number: ") . $number . '
' . trans("hadith.result.book: ") . strip_tags($book) . '
' . trans("hadith.result.part: ") . strip_tags($part) . '
' . trans("hadith.result.chapter: ") . strip_tags($chapter) . '
' . trans("hadith.result.arabic text: ") . ($isLong ? (substr($arabic, 0, 1000) . "...") : strip_tags($arabic)) . (App::getLocale() != 'fa' ? '
' . trans("hadith.result.english text: ") . substr($english, 0, 100) . '...' : "") . '
' . trans("hadith.result.id: ") . $id2;
    }

    public static function normalizer($phrase): array|string
    {
        return str_replace(' ', '%20', $phrase);
    }

    public static function getStringNahj(mixed $category, mixed $number, mixed $title, mixed $persian = null, mixed $arabic = null, mixed $english = null, mixed $dashti = null, mixed $id, bool $isLong): string
    {
        $botType = Config::get('config.bot.type', 'bale');
        return '
' . trans("nahj.result.number: ") . $number . '
' . trans("nahj.result.category: ") . strip_tags($category) . '
' . trans("nahj.result.title: ") . strip_tags($title) . (App::getLocale() == 'fa' ? '
' . trans("nahj.result.translate: ") . strip_tags($persian) : "") . '
' . trans("nahj.result.arabic text: ") . ($isLong ? (substr($arabic, 0, 1000) . "...") : strip_tags($arabic)) . (App::getLocale() != 'fa' ? '
' . trans("nahj.result.english text: ") . substr($english, 0, 100) . '...' : "") . '
' . trans("nahj.result.id: ") . $id . " -> " . BotHelper::generateTextLink("/_id" . $id, "/_id" . $id, $botType) . "
" . trans("bot.next") . " -> " . BotHelper::generateTextLink("/_id" . $id + 1, "/_id" . $id + 1, $botType);
    }
}
