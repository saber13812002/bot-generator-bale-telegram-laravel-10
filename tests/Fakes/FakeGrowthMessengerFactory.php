<?php

namespace Tests\Fakes;

use App\Interfaces\Services\GrowthMessenger;
use App\Interfaces\Services\GrowthMessengerFactory;

class FakeGrowthMessengerFactory implements GrowthMessengerFactory
{
    public function __construct(private GrowthMessenger $messenger)
    {
    }

    public function make(string $token, string $origin): GrowthMessenger
    {
        return $this->messenger;
    }
}
