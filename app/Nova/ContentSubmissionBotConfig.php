<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class ContentSubmissionBotConfig extends Resource
{
    public static $model = \App\Models\ContentSubmissionBotConfig::class;

    public static $title = 'id';

    public static $search = [
        'id',
        'bot_id',
    ];

    public static $displayInNavigation = true;

    public static function label()
    {
        return 'تنظیمات ربات محتوای متنی';
    }

    public static function singularLabel()
    {
        return 'تنظیمات ربات محتوا';
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Bot', 'bot', Bot::class)
                ->sortable()
                ->rules('required'),

            Number::make('شناسه کانال', 'channel_chat_id')
                ->rules('required'),

            Number::make('شناسه گروه تایید', 'group_chat_id')
                ->nullable(),

            Number::make('تعداد تایید لازم', 'required_approvals')
                ->min(0)
                ->max(2)
                ->default(1),

            Select::make('پلتفرم', 'origin')
                ->options([
                    'telegram' => 'Telegram',
                    'bale' => 'Bale',
                ])
                ->displayUsingLabels()
                ->sortable(),
        ];
    }

    public function cards(NovaRequest $request)
    {
        return [];
    }

    public function filters(NovaRequest $request)
    {
        return [];
    }

    public function actions(NovaRequest $request)
    {
        return [];
    }
}
