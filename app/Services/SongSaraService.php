<?php

namespace App\Services;

use App\Models\RssCourse;
use App\Models\RssFeedWebOrigin;
use App\Models\SongsaraPost;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class SongSaraService
{

    /**
     * @param array $data
     * @param $randomId
     * @return string[]
     */
    public static function createAndGetResponseData(array $data, $randomId): array
    {

        // Use updateOrCreate to either update an existing record or create a new one
        RssFeedWebOrigin::create(
            [
                'media_id' => $randomId,
                'origin' => "songsara.net",
                'link' => "https://songsara.net/" . $randomId,
                'image' => $data['image'],
                'title' => $data['title'],
                'description' => $data['description'],
                'media_url' => $data['media_url'],
            ]
        );

        // Construct the OGG file URL

        // Prepare your response data
        $responseData = [
            'message' => 'Audio file is ready for download.',
            // Add any other data you want to include in the response
        ];

        return $responseData;
    }

    public static function callCrawlerPage(?int $randomId)
    {
        $origin = "songsara.net";
        $url = "https://songsara.net/" . $randomId;
        $mp3Url = "https://bots.pardisania.ir/notfound.mp3";

        // Fetch HTML content
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $htmlContent = curl_exec($ch);
        curl_close($ch);

        // Parse HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($htmlContent);
        $xpath = new DOMXPath($dom);

        // Find the audio player source
        $title = $xpath->query('//h2[@class="AL-Si"]');
        $description = $xpath->query('//div[@class="AR-Si"]');
        $imageUrl = "https://bots.pardisania.ir/awdiobuks.jpg";
        $audioSource = $xpath->query('//li[@data-src]'); // XPath to get the audio source
        $images = $dom->getElementsByTagName('img');


        // Prepare the return data
        $data = [
            'media_id' => $randomId,
            'origin' => $origin,
            'link' => $url,
            'image' => $imageUrl,
            'title' => $title->length > 0 ? trim($title->item(0)->textContent) : null,
            'description' => $description->length > 0 ? trim($description->item(0)->textContent) : null,
            'media_url' => $audioSource->length > 0 ? $audioSource->item(0)->getAttribute('data-src') : $mp3Url,
        ];
//        dd($data);
        // Return the data only if title is found
        if ($title->length > 0) {
            return $data;
        }

        return null; // Return null if no title is found
    }


    public static function crawl($randomId)
    {
        $origin = "songsara.net";
        $url = "https://songsara.net/" . $randomId;
        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $htmlContent = curl_exec($ch);
        curl_close($ch);

        try {
            // Parse HTML
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($htmlContent);
            $xpath = new \DOMXPath($dom);

            // Extract song data
            $nodes = $xpath->query('/html/body/div[1]/div[2]/div[2]/aside//li');

            foreach ($nodes as $node) {
                $title = $xpath->query('.//span[@class="related-al-TOP"]', $node)->item(0)->nodeValue;
                $artist = $xpath->query('.//span[@class="related-ar"]', $node)->item(0)->nodeValue;
                $genre = $xpath->query('.//span[@class="related-ar"]', $node)->item(1)->nodeValue;
                $releaseDate = $xpath->query('.//span[@class="related-da"]', $node)->item(0)->nodeValue;
                $songUrl = $xpath->query('./a', $node)->item(0)->getAttribute('href');

                // Save to the database
                SongsaraPost::updateOrCreate(
                    ['url' => $songUrl],
                    [
                        'title' => $title,
                        'artist' => $artist,
                        'genre' => $genre,
                        'release_date' => $releaseDate,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public static function getDescriptionMediaUrlByMediaId(mixed $media_id)
    {

        $data = SongSaraService::callCrawlerPage($media_id);
        return $data;
    }
}
