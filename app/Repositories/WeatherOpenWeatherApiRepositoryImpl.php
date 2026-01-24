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
    public function call(float $latitude, float $longitude)
    {
        return self::callOpenWeatherMap($latitude, $longitude);
    }



    /**
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    private static function callOpenWeatherMap(float $latitude, float $longitude): mixed
    {
        $api_key = env("OPENWEATHER_API_TOKEN");
        $language = 'fa';

        $client = new GuzzleHttp\Client();
        // استفاده از coordinates به جای city name
        $response = $client->get('https://api.openweathermap.org/data/2.5/weather', [
            'query' => [
                'lat' => $latitude,
                'lon' => $longitude,
                'lang' => $language,
                'units' => 'metric',
                'appid' => $api_key
            ]
        ]);
        
        return json_decode($response->getBody(), true);
    }
}
