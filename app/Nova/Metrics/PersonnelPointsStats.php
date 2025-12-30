<?php

namespace App\Nova\Metrics;

use App\Models\Personnel;
use App\Models\Task;
use App\Models\MissionPersonnel;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Nova;

class PersonnelPointsStats extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        // Calculate total points from approved tasks in the selected range
        $taskPoints = $this->sum($request, Task::class, 'points', 'approved_at', function ($query) {
            return $query->where('task_status', 'approved');
        });

        // For missions, we'll calculate separately and add to task points
        // Note: This is a simplified approach. For more complex scenarios, 
        // we might need to create a custom query builder
        $taskPointsValue = is_object($taskPoints) ? $taskPoints->value : $taskPoints;
        
        // Get the date range from the request
        $range = $request->range ?? 30;
        $startDate = now()->subDays($range);
        
        $missionPoints = MissionPersonnel::where('status', 'approved')
            ->where('approved_at', '>=', $startDate)
            ->join('missions', 'mission_personnel.mission_id', '=', 'missions.id')
            ->sum('missions.points');

        $totalPoints = ($taskPointsValue ?? 0) + $missionPoints;
        
        return $this->result($totalPoints)
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
        return 'personnel-points-stats';
    }

    /**
     * Get the displayable name of the metric.
     *
     * @return string
     */
    public function name()
    {
        return 'مجموع امتیازها';
    }
}

