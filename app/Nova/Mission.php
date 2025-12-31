<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class Mission extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Mission>
     */
    public static $model = \App\Models\Mission::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'title';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'title',
        'description',
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

            BelongsTo::make('Tenant')
                ->sortable()
                ->rules('required'),

            Text::make('Title', 'title')
                ->sortable()
                ->rules('required', 'max:255'),

            Textarea::make('Description', 'description')
                ->nullable()
                ->alwaysShow(),

            BelongsTo::make('Prompt')
                ->nullable()
                ->sortable(),

            BelongsTo::make('Content')
                ->nullable()
                ->sortable(),

            Number::make('Points', 'points')
                ->sortable()
                ->default(0)
                ->rules('required', 'integer', 'min:0'),

            Number::make('Duration', 'duration')
                ->nullable()
                ->sortable()
                ->help('مدت زمان تخمینی ماموریت به دقیقه'),

            Number::make('Max Personnel', 'max_personnel')
                ->sortable()
                ->default(1)
                ->rules('required', 'integer', 'min:1'),

            Number::make('Current Personnel Count', 'current_personnel_count')
                ->sortable()
                ->default(0)
                ->rules('required', 'integer', 'min:0')
                ->readonly(),

            Select::make('Status', 'status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ])
                ->displayUsingLabels()
                ->default('active')
                ->sortable()
                ->rules('required'),
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
        return [];
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

