<?php

namespace App\Services;

use GuzzleHttp;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OneApiTranslationService
{
    private static array $providers = ["google", "microsoft", "targoman", "faraazin"];
    private static int $currentProviderIndex = 0;

    public function __construct()
    {
        //
    }

    /**
     * @throws \Exception|GuzzleHttp\Exception\GuzzleException
     */
    public static function call($text, $language)
    {
        $api_key = env("ONE_API_API_TOKEN");
        $client = new Client();

        $providersCount = count(self::$providers);
        for ($i = 0; $i < $providersCount; $i++) {
            $provider = self::$providers[self::$currentProviderIndex];
            $uri = 'https://one-api.ir/translate/?token=' . $api_key . '&action=' . $provider . '&lang=' . $language . '&q=' . urlencode($text);

            try {
                $response = $client->get($uri);
                if ($response->getStatusCode() == 200) {
                    self::$currentProviderIndex = (self::$currentProviderIndex + 1) % $providersCount;
                    return json_decode($response->getBody(), true);
                }
            } catch (\Exception $e) {
                // Log the error and try the next provider
                Log::warning($provider . ':' . $e->getMessage());
                // Log::error("Translation provider {$provider} failed: " . $e->getMessage());
            }

            // Move to the next provider in the list
            self::$currentProviderIndex = (self::$currentProviderIndex + 1) % $providersCount;
        }

        // If all providers fail, throw an exception or handle the error accordingly
        throw new \Exception('All translation providers failed.');
    }
}
