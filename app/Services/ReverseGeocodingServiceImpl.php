<?php

namespace App\Services;

use App\Interfaces\Services\ReverseGeocodingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverseGeocodingServiceImpl implements ReverseGeocodingService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/reverse';

    /**
     * دریافت آدرس از coordinates
     */
    public function getAddressFromCoordinates(float $latitude, float $longitude): ?array
    {
        $cacheKey = "reverse_geocode_{$latitude}_{$longitude}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($latitude, $longitude) {
            try {
                $response = Http::timeout(10)
                    ->get(self::NOMINATIM_URL, [
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'format' => 'json',
                        'addressdetails' => 1,
                        'accept-language' => 'fa,en',
                    ]);

                if (!$response->successful()) {
                    Log::warning('🌍 [ReverseGeocoding] API request failed', [
                        'status' => $response->status(),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ]);
                    return null;
                }

                $data = $response->json();

                if (!isset($data['address'])) {
                    return null;
                }

                $address = $data['address'];
                $displayName = $data['display_name'] ?? '';

                return [
                    'address' => $displayName,
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
                    'state' => $address['state'] ?? $address['province'] ?? null,
                    'country' => $address['country'] ?? null,
                    'country_code' => $address['country_code'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('🌍 [ReverseGeocoding] Error', [
                    'error' => $e->getMessage(),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
                return null;
            }
        });
    }
}
