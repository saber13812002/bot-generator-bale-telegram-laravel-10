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
            $message = $this->getMessageFromOpenWeatherMapApi();

            BotHelper::sendMessage($bot,$message);
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
        $weather_data = json_decode($response->getBody(), true);
        return $weather_data;
    }

    /**
     * @return string
     * @throws GuzzleException
     */
    public function getMessageFromOpenWeatherMapApi(): string
    {
        $weather_data = $this->callOpenWeatherMap();

        $message = $this->generateMessageByWeatherData($weather_data);
        return $message;
    }
}
