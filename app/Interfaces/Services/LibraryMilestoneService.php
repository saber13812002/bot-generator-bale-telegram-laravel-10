<?php

namespace App\Interfaces\Services;

use App\Models\BotUsers;
use Telegram;

/**
 * Listening-milestone reward engine.
 *
 * After every book delivery (increment of books_used) the delivery
 * services call registerDelivery(). When the user's books_used reaches
 * the armed reward_target we assume they "listened" to all books and
 * fire the win moment exactly once:
 *
 *   - grant bonus_multiplier * target books
 *   - apply the 100% code effect (library free to the end -> unlimited)
 *   - create an auto-activated symbolic discount code
 *   - send the celebratory message
 */
interface LibraryMilestoneService
{
    /**
     * Called by delivery services after books_used is incremented.
     * No-op when no milestone is armed, already granted, or not reached.
     *
     * @param Telegram   $bot       The (already authenticated) bot instance used to talk to the user
     * @param BotUsers   $botUser   The end user
     * @param int        $botId     Main bot id the subscription belongs to
     * @param string     $origin    'bale' or 'telegram'
     * @param string     $chatId    The user's chat id
     * @param bool       $viaReader Whether the audio was delivered through the reader bot
     */
    public function registerDelivery(
        Telegram $bot,
        BotUsers $botUser,
        int $botId,
        string $origin,
        string $chatId,
        bool $viaReader = false
    ): void;
}
