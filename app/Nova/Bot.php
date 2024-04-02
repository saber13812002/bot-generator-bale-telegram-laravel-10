<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Bot extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Bot>
     */
    public static $model = \App\Models\Bot::class;

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
     * @param NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('telegram_owner_chat_id')
            ->sortable(),

            Text::make('telegram_bot_name')
            ->sortable(),

            Text::make('telegram_bot_token')
            ->sortable(),

            Text::make('telegram_get_me_api_response')
            ->sortable(),

            Text::make('telegram_bot_status')
            ->sortable(),

            Text::make('telegram_webhook_is_set')
            ->sortable(),

            Text::make('bale_owner_chat_id')
            ->sortable(),

            Text::make('bale_bot_name')
            ->sortable(),

            Text::make('bale_bot_token')
            ->sortable(),

            Text::make('bale_get_me_api_response')
            ->sortable(),

            Text::make('bale_bot_status')
            ->sortable(),

            Text::make('bale_webhook_is_set')
            ->sortable(),

            Text::make('block_strategy')
            ->sortable(),

            Text::make('supported_message_types')
            ->sortable(),

            Text::make('supported_message_template')
            ->sortable(),

        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
