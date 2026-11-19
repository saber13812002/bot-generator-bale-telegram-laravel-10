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
        $sender = $this->mediaSender ?? app(ChannelPosterMediaSender::class);

        // Phase 5: config-driven — پلتفرم‌های native (soroush/rubika)
        // کلاینت داخلی دارند؛ publisher مبدأ آن‌ها NativeChannelPosterPublisher است.
        if (SyncPlatformRegistry::publisher($origin) === 'native') {
            return new NativeChannelPosterPublisher($token, $origin, $sender);
        }

        $bot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

        return new TelegramChannelPosterPublisher($bot, $origin, $sender);
    }
}
