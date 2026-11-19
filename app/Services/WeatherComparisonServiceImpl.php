<?php

namespace App\Services;

use App\Interfaces\Services\WeatherComparisonService;
use App\Models\WeatherHistory;

class WeatherComparisonServiceImpl implements WeatherComparisonService
{
    /**
     * مقایسه با دیروز
     */
    public function compareWithYesterday(array $currentWeather, array $location): array
    {
        $previous = WeatherHistory::getYesterdayWeather(
            $location['latitude'],
            $location['longitude']
        );

        return [
            'temperature_change' => $this->getTemperatureChange($currentWeather, $previous?->weather_data),
            'precipitation_change' => $this->getPrecipitationChange($currentWeather, $previous?->weather_data),
            'wind_change' => $this->getWindChange($currentWeather, $previous?->weather_data),
            'previous_data' => $previous?->weather_data,
        ];
    }

    /**
     * تغییر دما
     */
    public function getTemperatureChange(array $current, ?array $previous): ?float
    {
        $currentTemp = $current['temperature'] ?? $current['values']['temperature'] ?? null;
        $previousTemp = $previous['temperature'] ?? $previous['values']['temperature'] ?? null;

        if ($currentTemp === null || $previousTemp === null) {
            return null;
        }

        return (float) $currentTemp - (float) $previousTemp;
    }

    /**
     * تغییر بارش
     */
    public function getPrecipitationChange(array $current, ?array $previous): ?float
    {
        $currentPrecip = $current['precipitation'] ?? $current['rainIntensity'] ?? $current['values']['rainIntensity'] ?? null;
        $previousPrecip = $previous['precipitation'] ?? $previous['rainIntensity'] ?? $previous['values']['rainIntensity'] ?? null;

        if ($currentPrecip === null || $previousPrecip === null) {
            return null;
        }

        return (float) $currentPrecip - (float) $previousPrecip;
    }

    /**
     * تغییر باد
     */
    public function getWindChange(array $current, ?array $previous): ?float
    {
        $currentWind = $current['windGust'] ?? $current['windSpeed'] ?? $current['values']['windGust'] ?? $current['values']['windSpeed'] ?? null;
        $previousWind = $previous['windGust'] ?? $previous['windSpeed'] ?? $previous['values']['windGust'] ?? $previous['values']['windSpeed'] ?? null;

        if ($currentWind === null || $previousWind === null) {
            return null;
        }

        return (float) $currentWind - (float) $previousWind;
    }
}
