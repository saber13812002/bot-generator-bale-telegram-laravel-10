<?php

namespace App\Interfaces\Repositories;

interface WeatherTomorrowApiRepository
{
    public function call(float $latitude, float $longitude);
}
