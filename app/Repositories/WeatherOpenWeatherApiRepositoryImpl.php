<?php

namespace App\Repositories;

use App\Interfaces\Repositories\WeatherOpenWeatherApiRepository;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;

class WeatherOpenWeatherApiRepositoryImpl implements WeatherOpenWeatherApiRepository
{
    /**
     * @throws GuzzleException
     */
    public function call()
    {
        return self::callOpenWeatherMap();
    }



    /**
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    private static function callOpenWeatherMap(): mixed
    {
        $api_key = env("OPENWEATHER_API_TOKEN");
        $city_name = "Qom";
        $language = 'fa';

        $client = new GuzzleHttp\Client();
        $response = $client->get('https://api.openweathermap.org/data/2.5/weather?q=' . $city_name . '&lang=' . $language . '&units=metric&appid=' . $api_key);
//        echo $request->getStatusCode(); // 200
        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }
}
