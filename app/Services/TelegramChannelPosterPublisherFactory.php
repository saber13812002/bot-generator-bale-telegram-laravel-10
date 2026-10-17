<?php

namespace App\Services;

use App\Interfaces\Services\ChannelPosterPublisher;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use Telegram;

class TelegramChannelPosterPublisherFactory implements ChannelPosterPublisherFactory
{
    public function __construct(
        private ?ChannelPosterMediaSender $mediaSender = null
    ) {
    }

    public function make(string $token, string $origin): ChannelPosterPublisher
    {
        $bot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

        $sender = $this->mediaSender ?? app(ChannelPosterMediaSender::class);

        return new TelegramChannelPosterPublisher($bot, $origin, $sender);
    }
}
