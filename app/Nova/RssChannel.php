<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Spatie\TagsField\Tags;

class RssChannel extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\RssChannel>
     */
    public static $model = \App\Models\RssChannel::class;

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
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('title')->sortable()->rules('required', 'max:255'),
            Text::make('slug')->sortable()->rules('required', 'max:255'),
            Text::make('token')->sortable()->rules('required', 'max:255'),
            Text::make('target_id')->sortable()->rules('required', 'max:50'),

            Textarea::make('sign')->alwaysShow(),
//            Text::make('type'),

            Boolean::make('has_command'),

            Select::make('type')
                ->options([
                    'unknown' => 'Unknown',
                    'channel' => 'Channel',
                    'group' => 'Group',
                    'private' => 'Private',
                ])
                ->displayUsingLabels(),
            Tags::make('Tags'),

            BelongsTo::make('RssChannelOrigin')
                ->sortable()
                ->displayUsing(function ($item) {
                    return $item->name;
                }),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
