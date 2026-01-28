<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class WebhookEndpoint extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\WebhookEndpoint>
     */
    public static $model = \App\Models\WebhookEndpoint::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'endpoint_id',
        'name',
        'description',
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

            Text::make('Endpoint ID', 'endpoint_id')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('شناسه یکتای endpoint (مثلاً webhook-personnel-registration)'),

            Text::make('Name', 'name')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('نام endpoint'),

            Text::make('Route', 'route')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('مسیر endpoint (مثلاً /api/webhook-personnel-registration)'),

            Textarea::make('Description', 'description')
                ->help('توضیحات کوتاه endpoint'),

            Textarea::make('Detailed Description', 'detailed_description')
                ->help('توضیحات کامل و جامع ربات')
                ->rows(5),

            Image::make('Image', 'image_path')
                ->disk('public')
                ->path('bots')
                ->help('آپلود عکس ربات (ذخیره در storage/public/bots)'),

            Text::make('Image URL', 'image_url')
                ->help('URL خارجی عکس (در صورت استفاده از عکس خارجی)')
                ->placeholder('https://example.com/image.jpg'),

            KeyValue::make('Features', 'features')
                ->help('لیست ویژگی‌های ربات (کلید: عنوان، مقدار: توضیحات)')
                ->keyLabel('عنوان')
                ->valueLabel('توضیحات'),

            Textarea::make('Usage Instructions', 'usage_instructions')
                ->help('دستورالعمل استفاده از ربات')
                ->rows(4),

            Textarea::make('Technical Details', 'technical_details')
                ->help('جزئیات فنی ربات')
                ->rows(4),

            Boolean::make('Requires Bot Mother ID', 'requires_bot_mother_id')
                ->sortable(),

            Boolean::make('Requires Token', 'requires_token')
                ->sortable(),

            Boolean::make('Requires Language', 'requires_language')
                ->sortable(),

            Boolean::make('Supports Multiple Languages', 'supports_multiple_languages')
                ->sortable(),

            Boolean::make('Is Active', 'is_active')
                ->sortable()
                ->default(true),

            BelongsToMany::make('Related Bots', 'relatedBots', WebhookEndpoint::class)
                ->help('ربات‌های مرتبط که در صفحه جزئیات نمایش داده می‌شوند')
                ->fields(function () {
                    return [
                        Text::make('Order', 'order')
                            ->help('ترتیب نمایش (عدد کمتر = اولویت بیشتر)')
                            ->default(0),
                    ];
                }),
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
