<?php

namespace App\Http\Controllers;

use App\Helpers\BlogHelper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        if ($request->to == env("KAVENEGAR_SENDER") && $request->token == env("KAVENEGAR_API_KEY") && $request->from) {
            $mobileNumber = Str::substr($request->from, -10);
//            dd($mobileNumber);
            [$author_id, $blog_token] = BlogHelper::getBlogInfoByMobileNumber($mobileNumber);
            $messageId = "
" . $request->message_id;
            try {
                $response = BlogHelper::callApiPost($request->message . $messageId, $author_id, $blog_token);

            } catch (Exception $e) {
                $contains = Str::contains($e->getMessage(), 'slug');
                Log::info($e->getMessage());
                if ($contains) {
                    BlogHelper::callApiPost(rand(11111, 99999) . $request->message . $messageId, $author_id, $blog_token);
                    return "{\"error\":\"slug\"}";
                } else {
                    return "{\"error\":\"" . $e->getMessage() . "\"}";
                }
            }
        }

    }

}
