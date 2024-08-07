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
                $title = strip_tags((string)$item->title);
                $link = (string)$item->$unique_field_name;

                $description = isset($item->description) ? (string)$item->description : '';
                $textDescription = self::extractTextInTags($description);
                $imageUrl = self::extractImageUrl($description);

                // Initialize imageUrl
                if (!isset($imageUrl)) {
                    $imageUrl = null;
                }

                if (!$imageUrl && !empty($item->image)) {
                    $imageUrl = (string)$item->image; // Cast to string for safety
                    $link = (string)$item->link; // Cast to string for safety

//                    if (strpos($imageUrl, 'navaar.ir') !== false) {
//                        self::getAndSaveMediaIdIfNavaar($link, $imageUrl);
//                    }
                }

                $pubDate = Carbon::parse((string)$item->pubDate);

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
                        'description' => $textDescription,
                        'image_url' => $imageUrl, // Assuming there's an image_url column in your database
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
        if (empty($htmlContent)) {
            return '';
        }

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

    private static function extractImageUrl(string $htmlContent)
    {

        if (empty($htmlContent)) {
            return null;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        if ($images->length > 0) {
            return $images->item(0)->getAttribute('src');
        }

        return null;
    }

    /**
     * @param string $link
     * @param string $imageUrl
     * @return void
     */
    public static function getAndSaveMediaIdIfNavaar(string $link, string $imageUrl): void
    {
// Extract the audiobook ID
        preg_match('/https:\/\/www\.navaar\.ir\/audiobook\/(\d+)/', $link, $matches);

        if (isset($matches[1])) {
            $mediaId = $matches[1];

            preg_match('/https:\/\/www\.navaar\.ir\/content\/books\/([0-9a-fA-F\-]{36})/', $imageUrl, $matches2);

            if (isset($matches2[1])) {
                $audioBookId = $matches2[1];
                // Call the method from the audiobook service
                AudioBookService::saveAudioBookUUIdWithMediaId($mediaId, $audioBookId);
                // You can now use $audioBookDetails as needed
            }
        }
    }
}
