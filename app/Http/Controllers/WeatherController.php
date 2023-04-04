<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Http\Requests\StoreweatherRequest;
use App\Http\Requests\UpdateweatherRequest;
use App\Models\weather;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use Http\Factory\Guzzle\RequestFactory;
use Illuminate\Http\Request;
use Telegram;

class WeatherController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws GuzzleException
     */
    public function index(Request $request)
    {

        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_TELEGRAM"), 'telegram');
            }
//            $message = $this->getMessageFromOpenWeatherMapApi();
            $message = $this->getMessageFromTomorrowApi();

            BotHelper::sendMessage($bot, $message);
            BotHelper::sendMessageToSuperAdmin($message . "
چت آی دی:" . $bot->ChatID() . "
" . "
نام:" . $bot->FirstName() . "
" . "
نام خ:" . $bot->LastName() . "
", 'bale');
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
        return $this->find($description);
    }

    function find($mot): int|string
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

        $message = 'وضعیت هوا 🌬 در قم :
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
        return $message;
    }

    /**
     * @param mixed $weather_data
     * @return string
     */
    public function generateMessageByTomorrowData(mixed $weather_datas): string
    {
        $hours = 1;
        $raiseLimit = 0;
        $windSpeedLimit = 13;
//        dd($weather_datas);
        foreach ($weather_datas as $weather_data) {
            $hours++;
            if ($hours > 15) {
                if ($raiseLimit > 0) {
                    $message .= "


 گزارش سرعت باد در قم-فلکه ایران مرینوس در 15 ساعت آینده
 تعداد " . $raiseLimit . " بار سرعت بالای " . $windSpeedLimit . "
 گزارش شده است";
                } else {
                    $message = "هیچ گزارش سرعت بالای حد تعیین شده نداشتیم";
                }
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

            if ($weather_data["values"]['windSpeed'] > $windSpeedLimit) {
                $raiseLimit++;
                $message .= 'وضعیت قرمز 😥 باد 🌬 در ساعت :
 :' . $weather_data["startTime"] . '
 دید و برد چشم:' . $visibility . '
 تعداد ابرها:' . $clouds . '
 دمای هوا:' . $temp . '
 دمای هوا که احساس میشه:' . $feels_like . '
 رطوبت:' . $humidity . '
 فشار هوا:' . $pressure . '
 وضعیت باد 🌬 :.' . '
 💨 سرعت  :' . $weather_data["values"]['windSpeed'] . '
🧭 زاویه  : ' . $weather_data["values"]['windDirection'] . '
 🌪 وزش شدید  :' . $weather_data["values"]['windGust'];
            }
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
     * @throws GuzzleException
     */
    public function getMessageFromTomorrowApi(): string
    {
        try {
            $weather_data = $this->callTomorrow();
        } catch (\Exception $e) {
            return substr($e->getMessage(), -180);;
        }
        return $this->generateMessageByTomorrowData($weather_data['data']['timelines'][0]['intervals']);
    }

    private function callTomorrow()
    {
        $api_key = env("TOMORROW_API_TOKEN");

        $client = new GuzzleHttp\Client();
        $response = $client->get('https://api.tomorrow.io/v4/timelines?location=34.600209,50.828128&apikey=' . $api_key . '&units=metric&timesteps=1h&fields=temperature,windSpeed,windDirection,windGust,pressureSurfaceLevel,pressureSeaLevel,rainIntensity,visibility,cloudCover,uvIndex,humidity,weatherCode,temperatureApparent');
//        echo $request->getStatusCode(); // 200
        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
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
}
