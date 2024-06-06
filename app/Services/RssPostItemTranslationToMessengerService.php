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
        $rssPostItem = RssPostItem::whereDoesntHave('translations')
            ->with('rssItem')
            ->whereHas('rssItem', function ($query) {
                $query->where('is_active', true);
            })
            ->first();
//        dd($rssPostItem);
        if ($rssPostItem) {
            if ($rssPostItem->rssItem)
                if ($rssPostItem->rssItem->is_active) {
//                    dd($rssPostItem);
                    RssPostItemTranslationService::call($rssPostItem);
                }
        }
    }
}
