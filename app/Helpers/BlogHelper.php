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

        try {
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

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                \Log::warning('Blog API returned non-200 status', [
                    'status' => $statusCode,
                    'url' => config('blog.artisan'),
                    'body' => $response->getBody()->getContents()
                ]);
                return null;
            }

            return json_decode($response->getBody(), true);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            \Log::error('Blog API ClientException', [
                'url' => config('blog.artisan'),
                'status' => $response ? $response->getStatusCode() : 'unknown',
                'message' => $e->getMessage(),
                'body' => $response ? $response->getBody()->getContents() : 'N/A'
            ]);
            return null;
        } catch (GuzzleHttp\Exception\ServerException $e) {
            $response = $e->getResponse();
            \Log::error('Blog API ServerException', [
                'url' => config('blog.artisan'),
                'status' => $response ? $response->getStatusCode() : 'unknown',
                'message' => $e->getMessage()
            ]);
            return null;
        } catch (\Exception $e) {
            \Log::error('Blog API Exception', [
                'url' => config('blog.artisan'),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public static function callApiPost($text, $authorId, $blog_token)
    {
        $client = new GuzzleHttp\Client();
        $title = explode('.', $text, 118)[0];
        if (strlen($title) > 118)
            $title = substr($title, 0, 118);

        $request_param = [
            'title' => $title,
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
}
