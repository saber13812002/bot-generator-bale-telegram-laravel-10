<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\StoreweatherRequest;
use App\Http\Requests\UpdateweatherRequest;
use App\Models\weather;
use Carbon\Carbon;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Http\Factory\Guzzle\RequestFactory;
use Illuminate\Http\Request;
use Telegram;
use Hekmatinasser\Verta\Verta;


class WeatherController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws GuzzleException
     */
    public function index(Request $request)
    {
        $type = $request->input('origin');
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_TELEGRAM"), 'telegram');
            }
            $commands = "
=====================
برای استعلام
وضعیت هواشناسی فعلی دستور /current
و برای پیش بینی باد در 16 ساعت آینده /forcasting
را کلیک یا ارسال کنید.";
            if ($bot->Text() == "/current") {
                $message = $this->getMessageFromOpenWeatherMapApi();
            } else {
                $message = $this->getMessageFromTomorrowApi();
            }
            $this->sendMessageToUserAndAdmin($bot, $message . $commands, $type);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreweatherRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(weather $weather)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(weather $weather)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateweatherRequest $request, weather $weather)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(weather $weather)
    {
        //
    }

    private function convertWeatherDescriptionToPersian(mixed $description): int|string
    {
        return $this->mapStringOpenWeatherApi($description);
    }

    function mapStringOpenWeatherApi($mot): int|string
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
            if ($key == $mot) {
                return $value; // or return true;
            }
        }
        return -1;
    }

    /**
     * @param mixed $weather_data
     * @return string
     */
    public function generateMessageByWeatherData(mixed $weather_data): string
    {
        $weather_description = $this->convertWeatherDescriptionToPersian($weather_data["weather"][0]["description"]);
        $visibility = $weather_data["visibility"];
        $clouds = $weather_data["clouds"]["all"];
        $temp = $weather_data["main"]["temp"];
        $feels_like = $weather_data["main"]["feels_like"];
        $humidity = $weather_data["main"]["humidity"];
        $pressure = $weather_data["main"]["pressure"];

        return 'وضعیت هوا 🌬 در قم :
 :' . $weather_description . '
 دید و برد چشم:' . $visibility . '
 تعداد ابرها:' . $clouds . '
 دمای هوا:' . $temp . '
 دمای هوا که احساس میشه:' . $feels_like . '
 رطوبت:' . $humidity . '
 فشار هوا:' . $pressure . '
 وضعیت باد 🌬 :.' . '
 💨 سرعت  :' . $weather_data['wind']['speed'] . '
🧭 زاویه  : ' . $weather_data['wind']['deg'] . '
 🌪 وزش شدید  :' . $weather_data['wind']['gust'];
    }

    /**
     * @param mixed $weather_datas
     * @return string
     */
    public function generateMessageByTomorrowData(mixed $weather_datas): string
    {
        $hours = 1;
        $raiseLimit = 0;
        $windSpeedLimit = 10;
        $message = "";
//        dd($weather_datas);
        foreach ($weather_datas as $weather_data) {
            $originalStartDateTime = $weather_data["startTime"];
            $datetime = new Carbon($originalStartDateTime);

            $timezone = 'Asia/Tehran';
            $today5evening = Carbon::parse('today 5pm', $timezone);
            $tomorrow1am = Carbon::parse('tomorrow 1am', $timezone);

            $now = Carbon::now($timezone);

            if ($datetime->gte($now) && $datetime->gte($today5evening) && $datetime->lte($tomorrow1am)) {
                $jalaliStartDateTime = verta($datetime);

                $hours++;
                if ($hours > 15) {
                    return $message;
                }
//            dd($weather_data);
//            $weather_description = $this->convertWeatherTomorrowDescriptionToPersian($weather_data["rainIntensity"]);
                $weather_description = "";
                $visibility = $weather_data["values"]["visibility"];
                $clouds = $weather_data["values"]["cloudCover"];
                $temp = $weather_data["values"]["temperature"];
                $feels_like = $weather_data["values"]["temperatureApparent"];
                $humidity = $weather_data["values"]["humidity"];
                $pressure = $weather_data["values"]["pressureSeaLevel"];

                $windSpeed = $weather_data["values"]['windSpeed'];

                if ($windSpeed > $windSpeedLimit) {
                    $raiseLimit++;
                    if ($raiseLimit > 1) $message .= '
=====================';
                    $message .= '
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
🧭 زاویه  : ' . $weather_data["values"]['windDirection'] . '
 🌪 وزش شدید  :' . $weather_data["values"]['windGust'];
                }
            }

        }

        if ($raiseLimit > 0) {
            $message .= "


 گزارش از ساعت 5 امروز تا یک نصف شب امشب(1 بامداد) سرعت باد در قم-فلکه ایران مرینوس در ساعات آینده
 تعداد " . $raiseLimit . " بار سرعت بالای " . $windSpeedLimit . " کیلومتر بر ساعت
 گزارش شده است";
        } else {
            $message = "هیچ گزارش سرعت بالای حد تعیین شده نداشتیم";
        }

        return $message;
    }

    /**
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function callOpenWeatherMap(): mixed
    {
        $api_key = env("OPENWEATHER_API_TOKEN");
        $city_name = "Qom";

        $client = new GuzzleHttp\Client();
        $response = $client->get('https://api.openweathermap.org/data/2.5/weather?q=Qom&units=metric&appid=' . $api_key);
//        echo $request->getStatusCode(); // 200
        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }

    /**
     * @return string
     * @throws GuzzleException
     */
    public function getMessageFromOpenWeatherMapApi(): string
    {
        $weather_data = $this->callOpenWeatherMap();

        return $this->generateMessageByWeatherData($weather_data);
    }

    /**
     * @return string
     * @throws \Exception|GuzzleException
     */
    public function getMessageFromTomorrowApi(): string
    {
        try {
            $weather_data = $this->callTomorrow();
        } catch (\Exception $e) {
            return $this->findString($e->getMessage(), "Too Many Calls") ? substr($e->getMessage(), -180) : "خطای ناشناخته";;
            throw $e;
        }
        return $this->generateMessageByTomorrowData($weather_data['data']['timelines'][0]['intervals']);
    }

    /**
     * @throws GuzzleException
     */
    private function callTomorrow()
    {
        $api_key = env("TOMORROW_API_TOKEN");

        $client = new GuzzleHttp\Client();
        $baseUrl = env("APP_ENV") != "local" ? "https://api.tomorrow.io" : "http://localhost:3002";
        $uri = $baseUrl . '/v4/timelines?location=34.600209,50.828128&apikey=' . $api_key . '&units=metric&timesteps=1h&fields=temperature,windSpeed,windDirection,windGust,pressureSurfaceLevel,pressureSeaLevel,rainIntensity,visibility,cloudCover,uvIndex,humidity,weatherCode,temperatureApparent';
//        dd($uri);
        $response = $client->get($uri);
//        echo $request->getStatusCode(); // 200
        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }

    /**
     * @param Telegram $bot
     * @param string $message
     * @param $type
     * @return void
     */
    public function sendMessageToUserAndAdmin(Telegram $bot, string $message, $type): void
    {
        BotHelper::sendMessage($bot, $message);
        BotHelper::sendMessageToSuperAdmin($message . "
چت آی دی:" . $bot->ChatID() . "
" . "
نام:" . $bot->FirstName() . "
" . "
نام خ:" . $bot->LastName() . "
" . "
مرجع:" . $type . "
", 'bale');
    }

    private function convertWeatherTomorrowDescriptionToPersian(mixed $rainIntensity): string
    {
        $translate = [0 => "آسمان صاف☀️",
            1 => "کمی ابری🌤",
            2 => "ابرهای پراکنده⛅️",
            3 => "ابرهای شکسته🌤",
            4 => "باران نرم⛈",
            5 => "باران🌧",
            6 => "رعد و برق⚡️",
            7 => "برف❄️",
            8 => "مه🌫"];

        if ($rainIntensity < 5) {
            return $translate[0];
        } elseif ($rainIntensity < 10) {
            return $translate[1];
        } elseif ($rainIntensity < 20) {
            return $translate[2];
        }
        return "وضعیت مشخص نیست";
    }


    private function findString($string, $subString): bool
    {
        if (str_contains($string, $subString)) {
            return true;
        }
        return false;
    }
}
