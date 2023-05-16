<?php

namespace App\Helpers;

use App\Models\BlogUser;
use Carbon\Carbon;
use GuzzleHttp;

class BlogHelper
{
    public static function callApiMe(): void
    {

    }

    public static function callApiPost($text, $authorId, $blog_token)
    {
        $client = new GuzzleHttp\Client();

        $request_param = [
            'title' => explode('.', $text, 118)[0],
            'content' => str_replace('....', "<br>", $text),
            'posted_at' => Carbon::now(),
            'author_id' => $authorId,
            'thumbnail_id' => ''
        ];

        $request_data = json_encode($request_param);

//        dd($request_data);

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
        return $blogUser->count() > 0 ? [$blogUser['blog_user_id'], $blogUser['blog_token']] : [null, null];
    }


    public static function getBlogInfoByMobileNumber(string $mobile): array
    {
        $blogUser = BlogUser::query()
            ->whereMobileNumber($mobile)
            ->get()
            ->first();
        return $blogUser->count() > 0 ? [$blogUser['blog_user_id'], $blogUser['blog_token']] : [null, null];
    }
}
