<?php

namespace App\Helpers;

use App\Models\RssCourse;


use DOMDocument;
use DOMXPath;


class WebPageMediaFindSave
{

    public static function fetchAndSaveMp3UrlTest()
    {
        $url = 'https://songsara.net/162893/';
        return self::fetchAndSaveMp3Url($url);
    }

    public static function fetchAndSaveMp3Url($url)
    {
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
        $audioSources = $xpath->query('//div[@class="audioplayer-source"]');

        if ($audioSources->length > 0) {
            $mp3Url = $audioSources->item(0)->getAttribute('data-src');

            // Save to database
            RssCourse::updateOrCreate(
                ['url' => $url], // Unique constraint on URL
                ['image_url' => $mp3Url]
            );
        }

        return $mp3Url;
    }

}
