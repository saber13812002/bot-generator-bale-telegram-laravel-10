<?php

namespace App\Services;

use App\Builders\BotBuilder;
use App\Helpers\BotHelper;
use App\Interfaces\RssChannelMessageSender;
use Telegram;

/**
 * پیاده‌سازی پیش‌فرض ارسال پیام/رسانه به کانال RSS (via BotHelper/BotBuilder).
 *
 * در تست‌ها با fake جایگزین می‌شود:
 *   $this->app->bind(\App\Interfaces\RssChannelMessageSender::class, FakeRssChannelMessageSender::class);
 */
class BotRssChannelMessageSender implements RssChannelMessageSender
{
    public function sendBaseMessage(string $message, string $token, $target_id, string $mediumSlug, ?string $parseMode = null): mixed
    {
        return BotHelper::sendMessageEitaaSupport($message, $token, $target_id, $mediumSlug, $parseMode);
    }

    public function sendPhoto(string $token, $target_id, string $photoUrl, string $caption, string $title, string $mediumSlug, ?string $parseMode = null): mixed
    {
        $messenger = new Telegram($token, $mediumSlug);
        $builder = (new BotBuilder($messenger))
            ->setChatId($target_id)
            ->setCaption($caption)
            ->setTitle($title)
            ->setImageUrl($photoUrl);

        if ($parseMode !== null) {
            $builder->setParseMode($parseMode);
        }

        return $builder->sendPhoto();
    }

    public function sendAudio(string $token, $target_id, string $audioUrl, string $caption, string $title, string $mediumSlug, ?string $parseMode = null): mixed
    {
        $messenger = new Telegram($token, $mediumSlug);
        $builder = (new BotBuilder($messenger))
            ->setChatId($target_id)
            ->setCaption($caption)
            ->setTitle($title)
            ->setAudioUrl($audioUrl);

        if ($parseMode !== null) {
            $builder->setParseMode($parseMode);
        }

        return $builder->sendAudio();
    }
}
