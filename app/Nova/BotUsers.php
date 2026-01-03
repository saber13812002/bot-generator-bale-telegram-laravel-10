<?php

namespace App\Nova;

use App\Nova\Metrics\BotUsersPerDay;
use App\Nova\Metrics\BotUsersPerPlan;
use App\Nova\Metrics\NewBotUsers;
use App\Nova\Metrics\NewBotUsersProgress;
use App\Nova\Metrics\NewReleasesBotUsers;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class BotUsers extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\BotUsers>
     */
    public static $model = \App\Models\BotUsers::class;

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

            Text::make('chat_id')
                ->sortable(),

            Text::make('bot_id')
                ->sortable(),

            Text::make('status')
                ->sortable(),

            Text::make('origin')
                ->sortable(),

            Text::make('alias_name')
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

            new NewReleasesBotUsers,
            new NewBotUsers,
            new NewBotUsersProgress(),
            new BotUsersPerDay(),
            new BotUsersPerPlan(),
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
