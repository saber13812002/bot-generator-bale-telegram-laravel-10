<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class BlogUser extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\BlogUser>
     */
    public static $model = \App\Models\BlogUser::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Bot Mother Id', 'bot_mother_id')
                ->sortable(),

            Text::make('Blog Token', 'blog_token')
                ->sortable(),

            Text::make('Blog User Id', 'blog_user_id')
                ->sortable(),

            Text::make("Chat Id", 'chat_id')
                ->sortable(),

            Text::make('Type', 'type')
                ->sortable(),

            Text::make('Language', 'language')
                ->sortable(),

            Text::make('Locale', 'locale')
                ->sortable(),

            // Number::make('Amount of Hostels in Country', function () {
            //     return Hostel::where('country_id', $this->country_id)
            //         ->distinct('type_id')
            //         ->whereMonth('created_at', now()->month)
            //         ->count();
            // })
            //     ->hideWhenCreating()
            //     ->hideWhenUpdating(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
