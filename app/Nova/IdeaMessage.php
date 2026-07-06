<?php

namespace App\Nova;

use App\Models\Idea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class IdeaMessage extends Resource
{
    public static $model = \App\Models\IdeaMessage::class;

    public static $title = 'message';

    public static $search = [
        'message', 'sender_name',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Idea', 'idea', Idea::class)
                ->searchable()
                ->sortable()
                ->help('ایده مرتبط — هنگام ثبت از داخل صفحه جزئیات ایده، خودکار مقداردهی می‌شود'),

            Select::make('Sender', 'sender_type')
                ->options([
                    'user' => '👤 کاربر',
                    'admin' => '🛠 ادمین',
                ])
                ->displayUsingLabels()
                ->default('admin')
                ->sortable()
                ->rules('required'),

            Text::make('Sender Name', 'sender_name')
                ->nullable()
                ->hideFromIndex()
                ->help('نام فرستنده (اختیاری)'),

            Textarea::make('Message', 'message')
                ->alwaysShow()
                ->rules('required', 'max:5000'),

            DateTime::make('Sent', 'created_at')
                ->onlyOnDetail()
                ->sortable(),
        ];
    }

    public static function label(): string
    {
        return '💬 Messages';
    }

    public static function singularLabel(): string
    {
        return '💬 Message';
    }
}
