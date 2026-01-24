<?php

namespace App\Repositories;

use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;

class WeatherTomorrowApiRepositoryImpl implements WeatherTomorrowApiRepository
{

    /**
     * @throws GuzzleException
     */
    public function call(float $latitude, float $longitude)
    {
        return self::callTomorrow($latitude, $longitude);
    }


    /**
     * @throws GuzzleException
     */
    private static function callTomorrow(float $latitude, float $longitude)
    {
        $api_key = env("TOMORROW_API_TOKEN");
        
        if (!$api_key) {
            \Illuminate\Support\Facades\Log::error('🌤 [Weather] TOMORROW_API_TOKEN is not set');
            throw new \Exception('API key is not configured');
        }

        $client = new GuzzleHttp\Client();
        $baseUrl = "https://api.tomorrow.io";
        
        // استفاده از query parameters برای encoding بهتر
        $uri = $baseUrl . '/v4/timelines';
        $params = [
            'location' => "{$latitude},{$longitude}",
            'apikey' => $api_key,
            'units' => 'metric',
            'timesteps' => '1h',
            'fields' => 'temperature,windSpeed,windDirection,windGust,pressureSurfaceLevel,pressureSeaLevel,rainIntensity,visibility,cloudCover,uvIndex,humidity,weatherCode,temperatureApparent'
        ];
        
        $queryString = http_build_query($params);
        $fullUri = $uri . '?' . $queryString;
        
        \Illuminate\Support\Facades\Log::info('🌤 [Weather] Calling Tomorrow.io API', [
            'uri' => str_replace($api_key, '***', $fullUri)
        ]);

        try {
            $response = $client->get($fullUri);
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            \Illuminate\Support\Facades\Log::info('🌤 [Weather] API Response', [
                'status_code' => $statusCode,
                'body_length' => strlen($body),
                'body_preview' => substr($body, 0, 200)
            ]);
            
            $decoded = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Illuminate\Support\Facades\Log::error('🌤 [Weather] JSON decode error', [
                    'error' => json_last_error_msg(),
                    'body' => substr($body, 0, 500)
                ]);
                throw new \Exception('Invalid JSON response from API');
            }
            
            return $decoded;
        } catch (GuzzleException $e) {
            \Illuminate\Support\Facades\Log::error('🌤 [Weather] Guzzle Exception', [
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        }
    }
}
