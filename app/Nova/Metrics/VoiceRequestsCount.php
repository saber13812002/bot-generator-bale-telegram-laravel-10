<?php

namespace App\Nova\Metrics;

use App\Models\BotLog;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Nova;

class VoiceRequestsCount extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $range = $request->range;

        // Current period
        $currentCount = BotLog::where('created_at', '>=', $this->currentPeriodStart($range))
            ->where(function ($query) {
                $query->where('text', 'like', '%voice%')
                    ->orWhere('text', 'like', '%audio%')
                    ->orWhere('text', 'like', '%mp3%')
                    ->orWhere('command_type', 'voice')
                    ->orWhere('command_type', 'audio');
            })
            ->count();

        // Previous period
        $previousCount = BotLog::where('created_at', '>=', $this->previousPeriodStart($range))
            ->where('created_at', '<', $this->currentPeriodStart($range))
            ->where(function ($query) {
                $query->where('text', 'like', '%voice%')
                    ->orWhere('text', 'like', '%audio%')
                    ->orWhere('text', 'like', '%mp3%')
                    ->orWhere('command_type', 'voice')
                    ->orWhere('command_type', 'audio');
            })
            ->count();

        return $this->result($currentCount)
            ->previous($previousCount)
            ->prefix('')
            ->suffix('requests');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            1 => Nova::__('1 Day'),
            7 => Nova::__('7 Days'),
            30 => Nova::__('30 Days'),
            90 => Nova::__('90 Days'),
            180 => Nova::__('180 Days'),
            365 => Nova::__('365 Days'),
        ];
    }

    /**
     * Determine the start of the current period based on range.
     */
    protected function currentPeriodStart($range)
    {
        if ($range === 'TODAY') {
            return now()->startOfDay();
        }

        if ($range === 'MTD') {
            return now()->startOfMonth();
        }

        if ($range === 'QTD') {
            return now()->startOfQuarter();
        }

        if ($range === 'YTD') {
            return now()->startOfYear();
        }

        return now()->subDays((int) $range);
    }

    /**
     * Determine the start of the previous period based on range.
     */
    protected function previousPeriodStart($range)
    {
        if ($range === 'TODAY') {
            return now()->subDay()->startOfDay();
        }

        if ($range === 'MTD') {
            return now()->subMonth()->startOfMonth();
        }

        if ($range === 'QTD') {
            return now()->subQuarter()->startOfQuarter();
        }

        if ($range === 'YTD') {
            return now()->subYear()->startOfYear();
        }

        $days = (int) $range;
        return now()->subDays($days * 2);
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
        return 'voice-requests-count';
    }
}
