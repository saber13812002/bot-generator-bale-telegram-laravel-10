<?php

namespace App\Interfaces\Services;

interface WeatherOpenWeatherMapApiService
{
    public function getMessage(float $latitude, float $longitude): string;
}
