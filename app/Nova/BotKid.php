<?php

namespace App\Nova;

use App\Models\BotLog;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class BotKid extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\BotKid>
     */
    public static $model = \App\Models\BotKid::class;

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
        'token',
        'first_chat_id',
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
            Text::make('Bot Mother ID', 'bot_mother_id')->sortable(),
            Text::make('Token', 'token')
                ->onlyOnDetail()
                ->copyable(),
            Text::make('First Chat ID', 'first_chat_id')->sortable(),
            Text::make('Type', 'type')->sortable(),
            Text::make('Locale', 'locale')->sortable(),

            // ===== Activity Monitoring =====
            Badge::make('Health', function () {
                $firstChatId = $this->first_chat_id;
                $type = $this->type ?? 'bale';

                if (!$firstChatId) {
                    return 'untested';
                }

                $latestLog = BotLog::where('chat_id', $firstChatId)
                    ->where('origin', $type)
                    ->latest()
                    ->first();

                if (!$latestLog) {
                    return 'untested';
                }

                $hoursSince = $latestLog->created_at->diffInHours();

                if ($hoursSince <= 24) {
                    return 'healthy';
                } elseif ($hoursSince <= 72) {
                    return 'warning';
                } else {
                    return 'critical';
                }
            })->map([
                'healthy' => 'success',
                'warning' => 'warning',
                'critical' => 'danger',
                'untested' => 'info',
            ])->sortable(),

            Text::make('Last Activity', function () {
                $firstChatId = $this->first_chat_id;
                $type = $this->type ?? 'bale';

                if (!$firstChatId) {
                    return '—';
                }

                $latestLog = BotLog::where('chat_id', $firstChatId)
                    ->where('origin', $type)
                    ->latest()
                    ->first();

                return $latestLog ? $latestLog->created_at->diffForHumans() : 'No activity';
            })->sortable(),

            Text::make('24h Activity', function () {
                $firstChatId = $this->first_chat_id;
                $type = $this->type ?? 'bale';

                if (!$firstChatId) {
                    return '0';
                }

                return BotLog::where('chat_id', $firstChatId)
                    ->where('origin', $type)
                    ->where('created_at', '>=', now()->subDay())
                    ->count();
            })->sortable()->help('تعداد درخواست در ۲۴ ساعت'),
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
     * Get the filters available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
