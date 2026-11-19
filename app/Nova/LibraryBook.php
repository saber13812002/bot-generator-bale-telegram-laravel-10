<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class LibraryBook extends Resource
{
    public static $model = \App\Models\LibraryBook::class;

    public static $title = 'title';

    public static $search = ['title'];

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Bot', 'bot', Bot::class),
            Text::make('Title', 'title')->rules('required'),
            Textarea::make('Description', 'description')->nullable(),
            Boolean::make('Active', 'is_active'),
            Boolean::make('Random Eligible', 'random_eligible'),
            BelongsToMany::make('Genres', 'genres', LibraryGenre::class),
            HasMany::make('Media', 'media', LibraryBookMedia::class),
        ];
    }
}
