<?php

namespace App\Interfaces\Services;

interface ChannelPosterPublisherFactory
{
    public function make(string $token, string $origin): ChannelPosterPublisher;
}
