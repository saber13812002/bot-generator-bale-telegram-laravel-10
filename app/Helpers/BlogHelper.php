<?php

namespace App\Helpers;

use Carbon\Carbon;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;

class BlogHelper
{
    public static function callApiMe(): void
    {

    }

    public static function callApiPost($request)
    {
//        $client = new Client(['headers' => ['X-Client-Code' => env('KEY_CODE')]]);

        $client = new GuzzleHttp\Client();
//        dd($request->text);
        $request_param = [
            'title' => $request->text,
            'content' => $request->text,
            'posted_at' => Carbon::now(),
            'author_id' => $request->author_id,
            'thumbnail_id' => ''
        ];

        $request_data = json_encode($request_param);

        $response = $client->request(
            'POST',
            url(config('blog.post')),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env("Blog_TEST_TOKEN"),
                    'Content-Type' => 'application/json'
                ],
                'body' => $request_data
            ]
        );
//        dd(json_decode($response->getBody(), true));
        return json_decode($response->getBody(), true);
        // TODO: if slug existed
    }
}
