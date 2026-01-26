<?php

namespace App\Interfaces\Services;

use App\Models\BookPageScan;
use App\Models\BookPublishingQueue;

interface BookPublishingService
{
    /**
     * Add scan to publishing queue.
     */
    public function addToQueue(BookPageScan $scan, ?int $voiceId = null): BookPublishingQueue;

    /**
     * Get random pending item from queue.
     */
    public function getRandomPendingItem(int $botId): ?BookPublishingQueue;

    /**
     * Publish item to channels.
     */
    public function publishToChannels(BookPublishingQueue $queueItem, int $botId, string $type): bool;

    /**
     * Mark item as published.
     */
    public function markAsPublished(BookPublishingQueue $queueItem): bool;
}
