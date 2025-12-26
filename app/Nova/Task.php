<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class Task extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Task>
     */
    public static $model = \App\Models\Task::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'task_name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'task_name',
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

            Text::make('Task Name', 'task_name')
                ->sortable()
                ->rules('required', 'max:255'),

            BelongsTo::make('Personnel')
                ->sortable()
                ->rules('required'),

            Select::make('Task Status', 'task_status')
                ->options([
                    'reserved' => 'Reserved',
                    'in_progress' => 'In Progress',
                    'pending_approval' => 'Pending Approval',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->displayUsingLabels()
                ->default('reserved')
                ->sortable()
                ->rules('required'),

            Number::make('Points', 'points')
                ->sortable()
                ->default(0)
                ->rules('required', 'integer', 'min:0'),

            DateTime::make('Task Time', 'task_time')
                ->nullable()
                ->sortable(),

            DateTime::make('Assigned Time', 'assigned_time')
                ->nullable()
                ->sortable(),

            DateTime::make('Reserved Time', 'reserved_time')
                ->nullable()
                ->sortable(),

            Textarea::make('Final Link', 'final_link')
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

