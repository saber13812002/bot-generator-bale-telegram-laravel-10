<?php

namespace App\Services;

use App\Models\RssPostItem;

class RssPostItemTranslationToMessengerService
{
    public function __construct()
    {
        //
    }

    public static function run()
    {
        //        foreach ($rssPostItems as $rssPostItem) {
        $rssPostItem = RssPostItem::whereDoesntHave('translations')->first();
//        dd($rssPostItem);
        if ($rssPostItem)
            RssPostItemTranslationService::call($rssPostItem);
    }
}
