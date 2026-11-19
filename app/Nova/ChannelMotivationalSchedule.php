<?php

namespace App\Nova;

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class ChannelMotivationalSchedule extends Resource
{
    public static $model = \App\Models\ChannelMotivationalSchedule::class;

    public static $title = 'id';

    public static $search = [
        'id',
        'destination_id',
        'frequency',
    ];

    public static function label(): string
    {
        return '💬 Motivational Schedules';
    }

    public static function singularLabel(): string
    {
        return 'Motivational Schedule';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('کانال مقصد (ID)', 'destination_id')
                ->sortable(),

            Text::make('عنوان کانال', 'destination.channel_title')
                ->nullable()
                ->readonly()
                ->exceptOnForms(),

            Select::make('روز هفته', 'day_of_week')
                ->options([
                    '0' => 'یکشنبه',
                    '1' => 'دوشنبه',
                    '2' => 'سه‌شنبه',
                    '3' => 'چهارشنبه',
                    '4' => 'پنجشنبه',
                    '5' => 'جمعه',
                    '6' => 'شنبه',
                ])
                ->default('5')
                ->sortable(),

            Select::make('فراوانی', 'frequency')
                ->options([
                    'weekly'   => 'هفتگی',
                    'biweekly' => 'دوهفته‌ای',
                    'daily'    => 'روزانه',
                ])
                ->default('weekly')
                ->sortable(),

            Textarea::make('پرامپت (اختیاری)', 'prompt')
                ->nullable()
                ->hideFromIndex()
                ->alwaysShow(),

            Boolean::make('فعال', 'enabled')
                ->default(true)
                ->sortable(),

            DateTime::make('آخرین ارسال', 'last_sent_at')
                ->nullable()
                ->readonly(),

            Code::make('متن‌های اخیر', 'sent_texts')
                ->json()
                ->nullable()
                ->readonly()
                ->hideFromIndex(),

            Text::make('ساخته‌شده در', 'created_at')
                ->readonly()
                ->hideFromIndex(),
        ];
    }
}
