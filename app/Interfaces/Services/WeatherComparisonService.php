<?php

namespace App\Interfaces\Services;

interface WeatherComparisonService
{
    /**
     * مقایسه با دیروز
     * 
     * @param array $currentWeather
     * @param array $location ['latitude' => float, 'longitude' => float]
     * @return array ['temperature_change' => float, 'precipitation_change' => float, 'wind_change' => float]
     */
    public function compareWithYesterday(array $currentWeather, array $location): array;

    /**
     * تغییر دما
     */
    public function getTemperatureChange(array $current, ?array $previous): ?float;

    /**
     * تغییر بارش
     */
    public function getPrecipitationChange(array $current, ?array $previous): ?float;

    /**
     * تغییر باد
     */
    public function getWindChange(array $current, ?array $previous): ?float;
}
