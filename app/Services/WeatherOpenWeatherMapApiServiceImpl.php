<?php

namespace App\Services;

use App\Interfaces\Repositories\WeatherOpenWeatherApiRepository;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use GuzzleHttp\Exception\GuzzleException;

class WeatherOpenWeatherMapApiServiceImpl implements WeatherOpenWeatherMapApiService
{

    private WeatherOpenWeatherApiRepository $weatherOpenWeatherApiRepository;

    public function __construct(WeatherOpenWeatherApiRepository $weatherOpenWeatherApiRepository)
    {
        $this->weatherOpenWeatherApiRepository = $weatherOpenWeatherApiRepository;
    }

    /**
     * @throws GuzzleException
     */
    public function getMessage(): string
    {
        return $this->getMessageFromOpenWeatherMapApi();
    }


    /**
     * @return string
     * @throws GuzzleException
     */
    public function getMessageFromOpenWeatherMapApi(): string
    {
        $weather_data = $this->weatherOpenWeatherApiRepository->call();
        return self::generateMessageByWeatherData($weather_data);
    }


    /**
     * @param mixed $weather_data
     * @return string
     */
    public static function generateMessageByWeatherData(mixed $weather_data): string
    {
        $weather_description = $weather_data["weather"][0]["description"];
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
}
