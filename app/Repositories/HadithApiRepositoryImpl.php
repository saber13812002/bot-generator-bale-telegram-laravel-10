<?php

namespace App\Repositories;

use App\Interfaces\Repositories\HadithApiRepository;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;

class HadithApiRepositoryImpl implements HadithApiRepository
{

    /**
     * @throws GuzzleException
     */
    public function call()
    {
        return self::callHadithAcademyOfIslam();
    }


    /**
     * @throws GuzzleException
     */
    private static function callHadithAcademyOfIslam()
    {
//        $api_key = env("HADITH_API_TOKEN");

        $client = new GuzzleHttp\Client();
        $baseUrl = env("APP_ENV") != "local" ? "https://hadith.academyofislam.com" : "http://localhost:3000";
        $uri = $baseUrl . '/v1/narrations'.'?q=%D8%A7%D8%A8%D9%88%D8%A8%DA%A9%D8%B1&page=1&per_page=5';

        $response = $client->get($uri);
//        echo $request->getStatusCode(); // 200
        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }
}
