<?php

namespace App\Interfaces\Services;

interface WeatherTomorrowApiService
{
    public function getMessage(string $userText, float $latitude, float $longitude, bool $isBot = true): string;
}
