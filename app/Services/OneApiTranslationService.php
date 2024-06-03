<?php

namespace App\Services;

use GuzzleHttp;
class OneApiTranslationService
{
    public function __construct()
    {
        //
    }

    public static function call($text, $language)
    {
        $api_key = env("ONE_API_API_TOKEN");

        $client = new GuzzleHttp\Client();

        $uri = 'https://one-api.ir/translate/?token=' . $api_key . '&action=google&lang=' . $language . '&q=' . $text;
//        dd($uri);
        $response = $client->get($uri);
//        echo $response->getStatusCode(); // 200
//        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }
}
