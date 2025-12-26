<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class MissionPersonnel extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\MissionPersonnel>
     */
    public static $model = \App\Models\MissionPersonnel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
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

            BelongsTo::make('Mission')
                ->sortable()
                ->rules('required'),

            BelongsTo::make('Personnel')
                ->sortable()
                ->rules('required'),

            Select::make('Status', 'status')
                ->options([
                    'reserved' => 'Reserved',
                    'in_progress' => 'In Progress',
                    'pending_approval' => 'Pending Approval',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                ])
                ->displayUsingLabels()
                ->default('reserved')
                ->sortable()
                ->rules('required'),

            Textarea::make('Result Link', 'result_link')
                ->nullable()
                ->alwaysShow(),

            Number::make('Approval Message ID', 'approval_message_id')
                ->nullable()
                ->sortable(),

            Textarea::make('Rejection Reason', 'rejection_reason')
                ->nullable()
                ->alwaysShow(),

            Number::make('Approved By Chat ID', 'approved_by_chat_id')
                ->nullable()
                ->sortable(),

            DateTime::make('Approved At', 'approved_at')
                ->nullable()
                ->sortable(),

            DateTime::make('Rejected At', 'rejected_at')
                ->nullable()
                ->sortable(),

            DateTime::make('Started At', 'started_at')
                ->nullable()
                ->sortable(),

            DateTime::make('Completed At', 'completed_at')
                ->nullable()
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

