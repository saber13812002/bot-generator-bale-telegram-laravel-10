<?php

namespace App\Interfaces\Services;

interface ReverseGeocodingService
{
    /**
     * دریافت آدرس از coordinates
     * 
     * @param float $latitude
     * @param float $longitude
     * @return array|null ['address' => string, 'city' => string, 'country' => string]
     */
    public function getAddressFromCoordinates(float $latitude, float $longitude): ?array;
}
