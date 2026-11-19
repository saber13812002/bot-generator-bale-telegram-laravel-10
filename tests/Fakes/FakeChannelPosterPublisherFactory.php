<?php

namespace Tests\Fakes;

use App\Interfaces\Services\ChannelPosterPublisher;
use App\Interfaces\Services\ChannelPosterPublisherFactory;

class FakeChannelPosterPublisherFactory implements ChannelPosterPublisherFactory
{
    public function __construct(private ChannelPosterPublisher $publisher)
    {
    }

    public function make(string $token, string $origin): ChannelPosterPublisher
    {
        return $this->publisher;
    }
}
