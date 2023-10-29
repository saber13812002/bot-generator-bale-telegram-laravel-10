<?php

namespace App\Repositories;

use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;

class WeatherTomorrowApiRepositoryImpl implements WeatherTomorrowApiRepository
{

    /**
     * @throws GuzzleException
     */
    public function call()
    {
        return self::callTomorrow();
    }


    /**
     * @throws GuzzleException
     */
    private static function callTomorrow()
    {
        $api_key = env("TOMORROW_API_TOKEN");

        $client = new GuzzleHttp\Client();
        $baseUrl = env("APP_ENV") != "local" ? "https://api.tomorrow.io" : "http://localhost:3002";
        $uri = $baseUrl . '/v4/timelines?location=34.600209,50.828128&apikey=' . $api_key . '&units=metric&timesteps=1h&fields=temperature,windSpeed,windDirection,windGust,pressureSurfaceLevel,pressureSeaLevel,rainIntensity,visibility,cloudCover,uvIndex,humidity,weatherCode,temperatureApparent';

        $response = $client->get($uri);
//        echo $request->getStatusCode(); // 200
//        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }
}
