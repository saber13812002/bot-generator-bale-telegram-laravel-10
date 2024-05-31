<?php

namespace App\Nova;

use App\Nova\Metrics\BotLogPerBotMother;
use App\Nova\Metrics\BotLogPerChatId;
use App\Nova\Metrics\BotLogPerDay;
use App\Nova\Metrics\BotLogPerIsCommand;
use App\Nova\Metrics\BotLogPerLanguage;
use App\Nova\Metrics\BotLogPerMessengerType;
use App\Nova\Metrics\BotLogPerText;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class BotLog extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\BotLog>
     */
    public static $model = \App\Models\BotLog::class;

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
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('webhook_endpoint_uri'),
            Text::make('bot_mother_id'),
            Text::make('language'),
            Text::make('locale'),
            Text::make('type'),
            Text::make('text'),
//                ->displayUsing(function ($text) {
//                if (strlen($text) > 20) {
//                    return substr($text, 0, 20) . '...';
//                }
//                return $text;
//            }),
            Text::make('is_command'),
            Text::make('channel_group_type'),
            Text::make('bot_id'),
            Text::make('chat_id'),
            Text::make('message_id'),
            Text::make('from_id'),
            Text::make('from_chat_id'),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [
            new BotLogPerDay(),
            new BotLogPerMessengerType(),
            new BotLogPerLanguage(),
            new BotLogPerBotMother(),
            new BotLogPerChatId(),
            new BotLogPerIsCommand(),
            new BotLogPerText(),

        ];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
