<?php

namespace App\Services;

use App\Models\RssPostItem;

class RssPostItemToMessengerService
{
    public function __construct()
    {
        //
    }

    public static function call(RssPostItem $rssPostItem)
    {

        $title = TranslationService::call($rssPostItem->title);
        $content = TranslationService::call($rssPostItem->description);

        $rssPostItem->translations()->create([
            'locale' => 'fa',
            'title' => $title,
            'content' => $content,
        ]);
    }

}
