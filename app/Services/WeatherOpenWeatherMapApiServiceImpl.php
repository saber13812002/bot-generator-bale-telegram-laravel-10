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
    public function getMessage(float $latitude, float $longitude): string
    {
        return $this->getMessageFromOpenWeatherMapApi($latitude, $longitude);
    }


    /**
     * @param float $latitude
     * @param float $longitude
     * @return string
     * @throws GuzzleException
     */
    public function getMessageFromOpenWeatherMapApi(float $latitude, float $longitude): string
    {
        $weather_data = $this->weatherOpenWeatherApiRepository->call($latitude, $longitude);
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

        $windSpeed = $weather_data['wind']['speed'] ?? 0;
        $windDeg = $weather_data['wind']['deg'] ?? 0;
        $windGust = $weather_data['wind']['gust'] ?? 'N/A';

        return 'وضعیت هوا 🌬 در قم :
 :' . $weather_description . '
 دید و برد چشم:' . $visibility . '
 تعداد ابرها:' . $clouds . '
 دمای هوا:' . $temp . '
 دمای هوا که احساس میشه:' . $feels_like . '
 رطوبت:' . $humidity . '
 فشار هوا:' . $pressure . '
 وضعیت باد 🌬 :.' . '
 💨 سرعت  :' . $windSpeed . '
🧭 زاویه  : ' . $windDeg . '
 🌪 وزش شدید  :' . $windGust;
    }
}
