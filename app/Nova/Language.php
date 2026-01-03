<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Http\Requests\NovaRequest;

class Language extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Language>
     */
    public static $model = \App\Models\Language::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'display_name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'code',
        'name',
        'native_name',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Code', 'code')
                ->sortable()
                ->rules('required', 'max:10')
                ->help('کد زبان (مثلاً fa, en, ar-IQ)'),

            Text::make('Name', 'name')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('نام زبان'),

            Text::make('Native Name', 'native_name')
                ->sortable()
                ->help('نام بومی زبان'),

            Text::make('Flag Emoji', 'flag_emoji')
                ->help('ایموجی پرچم (مثلاً 🇮🇷, 🇬🇧)'),

            Text::make('Display Name', 'display_name')
                ->sortable()
                ->help('نمایش کامل با ایموجی (مثلاً 🇮🇷 فارسی)'),

            Boolean::make('Is Active', 'is_active')
                ->sortable()
                ->default(true),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
