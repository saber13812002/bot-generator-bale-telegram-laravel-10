<?php

namespace App\Nova\Metrics;

use App\Models\BotUsers;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class BotUsersPerBot extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->count($request, BotUsers::class, 'bot_id')
            ->label(function ($value) {
                // Try to find the bot name for the label
                $bot = \App\Models\Bot::find($value);
                if ($bot) {
                    $name = $bot->telegram_bot_name ?: $bot->bale_bot_name;
                    return $name ? "Bot #{$value} ({$name})" : "Bot #{$value}";
                }
                return "Bot #{$value}";
            });
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        return now()->addHours(6);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'bot-users-per-bot';
    }
}
