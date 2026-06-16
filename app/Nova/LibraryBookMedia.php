<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;

class LibraryBookMedia extends Resource
{
    public static $model = \App\Models\LibraryBookMedia::class;

    public static $title = 'id';

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Book', 'book', LibraryBook::class),
            Select::make('Type', 'type')->options([
                'audio' => 'Audio',
                'pdf' => 'PDF',
                'infographic' => 'Infographic',
            ])->displayUsingLabels(),
            URL::make('Content URL', 'content_url'),
            Text::make('Telegram File ID', 'telegram_file_id')->nullable()->readonly(),
            Text::make('Bale File ID', 'bale_file_id')->nullable()->readonly(),
        ];
    }
}
