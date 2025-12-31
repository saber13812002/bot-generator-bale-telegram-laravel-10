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

        try {
            // Fetch HTML content
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $htmlContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // بررسی خطاهای curl
            if ($curlError) {
                \Log::warning('CURL error in fetchAndSaveMp3Url', [
                    'url' => $url,
                    'error' => $curlError
                ]);
                return $mp3Url;
            }

            // بررسی HTTP status code
            if ($httpCode !== 200) {
                \Log::warning('HTTP error in fetchAndSaveMp3Url', [
                    'url' => $url,
                    'http_code' => $httpCode
                ]);
                return $mp3Url;
            }

            // بررسی خالی نبودن محتوا
            if (empty($htmlContent)) {
                \Log::warning('Empty HTML content in fetchAndSaveMp3Url', [
                    'url' => $url
                ]);
                return $mp3Url;
            }

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
        } catch (\Exception $e) {
            \Log::error('Exception in fetchAndSaveMp3Url', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $mp3Url;
        }
    }

}
