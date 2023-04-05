<?php

namespace App\Interfaces\Services;

interface WeatherTomorrowApiService
{
    public function getMessage(string $userText): string;
}
