<?php

namespace App\Services;

use App\Interfaces\Services\GrowthMessenger;
use App\Interfaces\Services\GrowthMessengerFactory;
use Telegram;

class TelegramGrowthMessengerFactory implements GrowthMessengerFactory
{
    public function make(string $token, string $origin): GrowthMessenger
    {
        $bot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

        return new TelegramGrowthMessenger($bot);
    }
}
