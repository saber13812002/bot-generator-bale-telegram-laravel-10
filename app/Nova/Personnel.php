<?php

namespace App\Nova;

use App\Nova\Metrics\NewPersonnel;
use App\Nova\Metrics\NewPersonnelProgress;
use App\Nova\Metrics\PersonnelPerDay;
use App\Nova\Metrics\PersonnelPerRank;
use App\Nova\Metrics\PersonnelPerTenant;
use App\Nova\Metrics\PersonnelTasksStats;
use App\Nova\Metrics\PersonnelMissionsStats;
use App\Nova\Metrics\PersonnelPointsStats;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Personnel extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Personnel>
     */
    public static $model = \App\Models\Personnel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'first_name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'first_name',
        'last_name',
        'national_code',
        'phone_number',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('First Name', 'first_name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Last Name', 'last_name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('National Code', 'national_code')
                ->sortable()
                ->rules('required', 'max:10', 'unique:personnel,national_code,{{resourceId}}'),

            Text::make('Phone Number', 'phone_number')
                ->sortable()
                ->rules('required', 'max:15'),

            BelongsTo::make('Tenant')
                ->sortable()
                ->rules('required'),

            Select::make('Rank', 'rank')
                ->options([
                    'سرباز صفر' => 'سرباز صفر',
                    'سرباز یک' => 'سرباز یک',
                    'سرباز دو' => 'سرباز دو',
                    'سرباز سه' => 'سرباز سه',
                ])
                ->displayUsingLabels()
                ->default('سرباز صفر')
                ->sortable(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [
            new NewPersonnel,
            new NewPersonnelProgress,
            new PersonnelPerDay,
            new PersonnelPerRank,
            new PersonnelPerTenant,
            new PersonnelTasksStats,
            new PersonnelMissionsStats,
            new PersonnelPointsStats,
        ];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}

