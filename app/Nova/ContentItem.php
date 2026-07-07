<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class ContentItem extends Resource
{
    public static $model = \App\Models\ContentItem::class;

    public static $title = 'title';

    public static $search = [
        'id', 'title',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Bot', 'bot', \App\Nova\Bot::class)
                ->searchable()
                ->sortable()
                ->help('رباط مرتبط'),

            BelongsTo::make('Category', 'category', \App\Nova\ContentCategory::class)
                ->searchable()
                ->sortable()
                ->help('دسته/تگ این آیتم'),

            Text::make('Title', 'title')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('عنوان آیتم (مثلاً نام کتاب صوتی)'),

            Number::make('Queue Order', 'queue_order')
                ->sortable()
                ->default(0)
                ->help('ترتیب در صف ارسال'),

            Boolean::make('Active', 'is_active')
                ->sortable()
                ->default(true),

            HasMany::make('Assets', 'assets', ContentAsset::class),

            DateTime::make('Created', 'created_at')
                ->onlyOnDetail(),

            DateTime::make('Updated', 'updated_at')
                ->onlyOnDetail(),
        ];
    }

    public static function label(): string
    {
        return '📦 Content Items';
    }

    public static function singularLabel(): string
    {
        return 'Content Item';
    }

    /**
     * فقط ربات‌هایی که endpoint محتوایی دارند نمایش بده
     */
    public static function relatableBots(NovaRequest $request, $query)
    {
        $contentEndpoints = config('content_bots.content_endpoint_ids', ['book-library']);

        return $query->whereIn('endpoint_id', $contentEndpoints);
    }
}
