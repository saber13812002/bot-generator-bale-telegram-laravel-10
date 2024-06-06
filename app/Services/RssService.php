<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Models\RssPostItem;
use DOMDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class RssService
{


    public function __construct()
    {
        //
    }

    public static function readRssAndSave($rssUrl, $id, $unique_field_name = null): JsonResponse
    {

        try {
            $response = Http::get($rssUrl);

//        if ($response->successful()) {
            $xml = simplexml_load_string($response->body());

            // Loop through the RSS items
//        dd($xml);
            foreach ($xml->channel->item as $item) {
                // Extract the necessary data from the RSS item
//            $rssItemId = $item->rss_item_id;
                $title = strip_tags($item->title);
                $link = ($item->$unique_field_name);
//                dd($item);
                $description = self::extractTextInTags($item->description);
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
            return self::findTheNewOrNonExistentItems($xml, $unique_field_name);

        } catch (\Exception $e) {
            BotHelper::sendMessageToBotAdmin(new \Telegram(env('BOT_HADITH_TOKEN_BALE')), "error in read xml as rss" . $e->getMessage());
        }

        return response()->json(null);
    }

    /**
     * @param SimpleXMLElement $xml
     * @param mixed $unique_field_name
     * @return JsonResponse
     */
    public static function findTheNewOrNonExistentItems(SimpleXMLElement $xml, mixed $unique_field_name): JsonResponse
    {
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

    private static function extractTextInTags($htmlContent): string
    {
        $text = '';
//        dd($htmlContent);
        $dom = new DOMDocument();
        $dom->loadHTML($htmlContent);

        $nodes = $dom->getElementsByTagName('*');
        foreach ($nodes as $node) {
            $text .= $node->nodeValue . ' ';
        }

        return $text;
    }
}
