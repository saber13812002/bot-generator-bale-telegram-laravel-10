<?php

namespace App\Nova;

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class WeeklyReadInvitation extends Resource
{
    public static $model = \App\Models\WeeklyReadInvitation::class;

    public static $title = 'id';

    public static $search = [
        'id',
        'destination_id',
        'max_post_id',
    ];

    public static function label(): string
    {
        return '📖 Weekly Read Invitations';
    }

    public static function singularLabel(): string
    {
        return 'Weekly Read Invitation';
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

            Number::make('بیشترین شماره مطلب', 'max_post_id')
                ->rules('required', 'integer', 'min:1')
                ->sortable(),

            Boolean::make('فعال', 'enabled')
                ->default(true)
                ->sortable(),

            DateTime::make('آخرین دعوت', 'last_invited_at')
                ->nullable()
                ->readonly(),

            Text::make('ساخته‌شده در', 'created_at')
                ->readonly()
                ->hideFromIndex(),
        ];
    }
}
