<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterPublisher;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use Telegram;

class TelegramChannelPosterPublisherFactory implements ChannelPosterPublisherFactory
{
    public function make(string $token, string $origin): ChannelPosterPublisher
    {
        $bot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

        return new TelegramChannelPosterPublisher($bot);
    }
}
