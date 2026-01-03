<?php

namespace App\Nova\Metrics;

use App\Models\MissionPersonnel;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Nova;

class PersonnelMissionsStats extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        // Get the date range from request
        $range = $request->input('range', 30);
        $startDate = now()->subDays($range);
        
        $count = MissionPersonnel::where('status', 'approved')
            ->where('approved_at', '>=', $startDate)
            ->count();
        
        return $this->result($count)
            ->format('0,0');
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
            365 => Nova::__('365 Days'),
        ];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
         return now()->addHours(12);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'personnel-missions-stats';
    }

    /**
     * Get the displayable name of the metric.
     *
     * @return string
     */
    public function name()
    {
        return 'ماموریت‌های تایید شده';
    }
}

