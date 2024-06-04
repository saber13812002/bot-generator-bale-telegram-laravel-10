<?php

namespace App\Services;

use App\Models\RssPostItem;
use Illuminate\Support\Str;

class RssPostItemTranslationService
{
    public function __construct()
    {
        //
    }

    public static function call(RssPostItem $rssPostItem)
    {
        if ($rssPostItem->rssItem && $rssPostItem->rssItem->locale != 'fa' && $rssPostItem->rssItem->target_locale != 'fa') {
            $title = TranslationService::call($rssPostItem->title);
            $content = TranslationService::call(substr($rssPostItem->description, 3500));
        } else {
            $title = $rssPostItem->title;
            $content = $rssPostItem->description;
        }

        $rssPostItem->translations()->create([
            'locale' => 'fa',
            'title' => strip_tags($title),
            'content' => strip_tags($content),
        ]);
    }

}
