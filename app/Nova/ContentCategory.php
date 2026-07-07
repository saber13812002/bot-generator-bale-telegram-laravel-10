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

class ContentCategory extends Resource
{
    public static $model = \App\Models\ContentCategory::class;

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
                ->help('رباط مرتبط با این دسته'),

            Text::make('Title', 'title')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('عنوان دسته/تگ (مثلاً: کتاب‌های صوتی، پادکست‌ها)'),

            Number::make('Sort Order', 'sort_order')
                ->sortable()
                ->default(0)
                ->help('ترتیب نمایش'),

            Boolean::make('Active', 'is_active')
                ->sortable()
                ->default(true),

            HasMany::make('Items', 'items', ContentItem::class),

            DateTime::make('Created', 'created_at')
                ->onlyOnDetail(),

            DateTime::make('Updated', 'updated_at')
                ->onlyOnDetail(),
        ];
    }

    public static function label(): string
    {
        return '🏷 Content Categories';
    }

    public static function singularLabel(): string
    {
        return 'Category';
    }

    /**
     * فقط ربات‌هایی که endpoint محتوایی دارند نمایش بده
     */
    public static function relatableBots(NovaRequest $request, $query)
    {
        $contentEndpoints = config('content_bots.content_endpoint_ids', ['book-library']);
        // همچنین ربات‌های book-library-reader را هم مجاز کن
        $contentEndpoints[] = 'book-library-reader';

        return $query->whereIn('endpoint_id', $contentEndpoints);
    }
}
