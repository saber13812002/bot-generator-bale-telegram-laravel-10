<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Models\RssPostItem;
use DOMDocument;
use DOMXPath;
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
//            dd($response);
            if (!$response->successful()) {
                throw new \Exception("Failed to fetch RSS feed.");
            }

            $xml = simplexml_load_string($response->body());
            if ($xml === false) {
                throw new \Exception("Failed to parse RSS feed.");
            }
//            dd($xml);
            foreach ($xml->channel->item as $item) {
//                dd($item);
                $title = strip_tags((string) $item->title);
                $link = (string) $item->$unique_field_name;

                $description = isset($item->description) ? self::extractTextInTags((string) $item->description) : '';

                $pubDate = Carbon::parse((string) $item->pubDate);

//                dd($title, $link, $description, $pubDate);
                $existingItem = RssPostItem::query()
                    ->where('link', $link)
                    ->first();
//                dd($existingItem);
                if (!$existingItem) {
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
            BotHelper::sendMessageToBotAdmin(new \Telegram(env('BOT_HADITH_TOKEN_BALE')), "Error reading XML as RSS: " . $e->getMessage());
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
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML parsing warnings
        $dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//text()'); // Fetch all text nodes

        $text = '';
        foreach ($nodes as $node) {
            $text .= $node->nodeValue . ' ';
        }

        return trim($text);
    }
}
