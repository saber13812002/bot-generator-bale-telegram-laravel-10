<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class ContentItem extends Resource
{
    public static $model = \App\Models\ContentItem::class;

    public static $title = 'title';

    public static $search = ['title'];

    public static function label(): string
    {
        return 'Content Items';
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Bot', 'bot', Bot::class),
            BelongsTo::make('Category', 'category', ContentCategory::class),
            Text::make('Title', 'title'),
            Number::make('Queue Order', 'queue_order'),
            Boolean::make('Active', 'is_active'),
            HasMany::make('Assets', 'assets', ContentAsset::class),
        ];
    }
}
