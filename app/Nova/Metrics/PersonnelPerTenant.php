<?php

namespace App\Nova\Metrics;

use App\Models\Personnel;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class PersonnelPerTenant extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->count($request, Personnel::class, 'tenant_id')
            ->label(function ($value) {
                $tenant = \App\Models\Tenant::find($value);
                return $tenant ? $tenant->name : "Tenant #{$value}";
            });
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
        return 'personnel-per-tenant';
    }
}

