<?php

namespace App\Helpers;

use Carbon\Carbon;
use GuzzleHttp;

class BlogHelper
{
    public static function callApiMe(): void
    {

    }

    public static function callApiPost($text, $authorId)
    {
        $client = new GuzzleHttp\Client();

        $request_param = [
            'title' => $text,
            'content' => $text,
            'posted_at' => Carbon::now(),
            'author_id' => $authorId,
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

        // TODO: if slug existed

        return json_decode($response->getBody(), true);
    }
}
