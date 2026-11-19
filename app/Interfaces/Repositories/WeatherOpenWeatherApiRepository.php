<?php

namespace App\Interfaces\Repositories;

interface WeatherOpenWeatherApiRepository
{
    public function call(float $latitude, float $longitude);
}
