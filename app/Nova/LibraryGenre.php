<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class LibraryGenre extends Resource
{
    public static $model = \App\Models\LibraryGenre::class;

    public static $title = 'name';

    public static $search = ['name'];

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Bot', 'bot', Bot::class),
            Text::make('Name', 'name')->rules('required'),
            Number::make('Page', 'page')->min(1)->max(10),
            Number::make('Sort Order', 'sort_order'),
            Boolean::make('Active', 'is_active'),
        ];
    }
}
