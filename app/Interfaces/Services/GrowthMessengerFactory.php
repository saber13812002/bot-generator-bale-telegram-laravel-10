<?php

namespace App\Interfaces\Services;

interface GrowthMessengerFactory
{
    public function make(string $token, string $origin): GrowthMessenger;
}
