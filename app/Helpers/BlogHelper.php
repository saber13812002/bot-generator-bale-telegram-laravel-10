<?php

namespace App\Helpers;

use App\Models\BlogUser;
use Carbon\Carbon;
use GuzzleHttp;

class BlogHelper
{
    public static function callArtisanQueueWork($blog_token): mixed
    {
        $client = new GuzzleHttp\Client();

        $request_param = [
            'posted_at' => Carbon::now()
        ];

        $request_data = json_encode($request_param);

        $response = $client->request(
            'POST',
            url(config('blog.artisan')),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $blog_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => $request_data
            ]
        );
        return json_decode($response->getBody(), true);
    }

    public static function callApiPost($text, $authorId, $blog_token)
    {
//        dd($text);

        $title = explode('.', $text, 118)[0];
        if (strlen($title) > 118)
            $title = substr($title, 0, 118);


//        dd($title, $text);

        $request_param = [
            'title' => strlen($text) > 118 ? substr($text, 0, 118) : $text,
            'content' => str_replace('....', "<br>", $text),
            'posted_at' => Carbon::now(),
            'author_id' => $authorId,
            'thumbnail_id' => ''
        ];

        $request_data = json_encode($request_param);

//        dd($request_data);

        $client = new GuzzleHttp\Client();
        $response = $client->request(
            'POST',
            url(config('blog.post')),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $blog_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => $request_data
            ]
        );
        // TODO: if slug existed
        return json_decode($response->getBody(), true);
    }


    public static function getBlogInfo(string $type, string $chatId): array
    {
        $blogUser = BlogUser::query()
            ->whereType($type)
            ->whereChatId($chatId)
            ->get()
            ->first();
        return ($blogUser && $blogUser->count() > 0) ? [$blogUser['blog_user_id'], $blogUser['blog_token']] : [null, null];
    }


    public static function getBlogInfoByMobileNumber(string $mobile): array
    {
        $blogUser = BlogUser::query()
            ->whereMobileNumber($mobile)
            ->get()
            ->first();
        return ($blogUser && $blogUser->count() > 0) ? [$blogUser['blog_user_id'], $blogUser['blog_token']] : [null, null];
    }

    public static function callApiGetUserMessenger($author_id, mixed $blogToken)
    {
        $client = new GuzzleHttp\Client();
        $response = $client->request(
            'GET',
            url(config('blog.messenger') . '/' . $author_id),
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $blogToken,
                    'Content-Type' => 'application/json'
                ]
            ]
        );
        // TODO: if slug existed
        return json_decode($response->getBody(), true);
    }
}
