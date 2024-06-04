<?php

namespace App\Services;

use App\Models\RssPostItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Laravel\App;

class RssService
{


    public function __construct()
    {
        //
    }

    public static function readRssAndSave($rssUrl, $id, $unique_field_name = null): \Illuminate\Http\JsonResponse
    {

        // Set the RSS feed URL
//        $rssUrl = 'https://example.com/rss';

        // Fetch the RSS feed
        $response = Http::get($rssUrl);
        $xml = simplexml_load_string($response->body());

        // Loop through the RSS items
//        dd($xml);
        foreach ($xml->channel->item as $item) {
            // Extract the necessary data from the RSS item
//            $rssItemId = $item->rss_item_id;
            $title = strip_tags($item->title);
            $link = strip_tags($item->$unique_field_name);
            $description = strip_tags($item->description);
            $pubDate = Carbon::parseFromLocale($item->pubDate);

            // Check if the item already exists in the database
            $existingItem = RssPostItem::query()
                ->whereLink($link)
                ->first();

            if (!$existingItem) {
                // If the item doesn't exist, save it to the database
                RssPostItem::query()->insert([
                    'rss_item_id' => $id,
                    'title' => $title,
                    'link' => $link,
                    'description' => $description,
                    'pub_date' => $pubDate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Find the new or non-existent items
        $newItems = RssPostItem::query()
            ->whereNotIn('link', function ($query) use ($xml, $unique_field_name) {
                $query->select('link')
                    ->from('rss_post_items')
                    ->whereIn('link', array_column(iterator_to_array($xml->channel->item), $unique_field_name));
            })
            ->get();

        return response()->json($newItems);
    }
}
