<?php

namespace App\Services;

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
        $response = $client->get('https://one-api.ir/translate/?token=' . $api_key . '&action=google&lang=' . $language . '&q=' . $text);
//        echo $request->getStatusCode(); // 200
//        echo $response->getBody()->getContents();
        return json_decode($response->getBody(), true);
    }
}
