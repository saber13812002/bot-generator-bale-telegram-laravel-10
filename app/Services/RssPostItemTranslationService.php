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
        $locale = 'en';
        if ($rssPostItem->rssItem && $rssPostItem->rssItem->locale != 'fa' && $rssPostItem->rssItem->target_locale == 'fa') {
            $locale = $rssPostItem->rssItem->target_locale;
            $title = TranslationService::call($rssPostItem->title);
            $content = $rssPostItem->description ? TranslationService::call(substr($rssPostItem->description, 0, 3500)) : "-";
        } else {
            $locale = 'fa';
            $title = $rssPostItem->title;
            $content = $rssPostItem->description;
        }

//        dd($title, $content);

        $rssPostItem->translations()->create([
            'locale' => $locale,
            'title' => strip_tags($title),
            'content' => ($content) ?? "-",
        ]);
    }

}
