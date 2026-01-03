<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Models\WebhookEndpoint;

class Bot extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Bot>
     */
    public static $model = \App\Models\Bot::class;

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
        'telegram_bot_name',
        'bale_bot_name',
        'endpoint_id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request): array
    {
        // دریافت لیست endpoint ها برای Select
        $endpointOptions = WebhookEndpoint::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'endpoint_id')
            ->toArray();

        return [
            ID::make()->sortable(),

            // فیلدهای جدید
            Select::make('Webhook Endpoint', 'endpoint_id')
                ->options($endpointOptions)
                ->searchable()
                ->displayUsingLabels()
                ->sortable()
                ->nullable()
                ->help('انتخاب endpoint که این ربات از روی آن ساخته شده است'),

            Select::make('Type', 'type')
                ->options([
                    'telegram' => 'Telegram',
                    'bale' => 'Bale',
                ])
                ->displayUsingLabels()
                ->sortable()
                ->nullable()
                ->help('نوع پیام‌رسان (تلگرام یا بله)'),

            Text::make('Language Code', 'language_code')
                ->sortable()
                ->nullable()
                ->help('کد زبان ربات (مثلاً fa, en, ar)')
                ->rules('max:10'),

            // فیلدهای موجود
            Text::make('Telegram Owner Chat ID', 'telegram_owner_chat_id')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Telegram Bot Name', 'telegram_bot_name')
                ->sortable(),

            Text::make('Telegram Bot Token', 'telegram_bot_token')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Telegram Get Me API Response', 'telegram_get_me_api_response')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Telegram Bot Status', 'telegram_bot_status')
                ->sortable(),

            Text::make('Telegram Webhook Is Set', 'telegram_webhook_is_set')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Bale Owner Chat ID', 'bale_owner_chat_id')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Bale Bot Name', 'bale_bot_name')
                ->sortable(),

            Text::make('Bale Bot Token', 'bale_bot_token')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Bale Get Me API Response', 'bale_get_me_api_response')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Bale Bot Status', 'bale_bot_status')
                ->sortable(),

            Text::make('Bale Webhook Is Set', 'bale_webhook_is_set')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Block Strategy', 'block_strategy')
                ->sortable(),

            Text::make('Supported Message Types', 'supported_message_types')
                ->sortable(),

            Text::make('Supported Message Template', 'supported_message_template')
                ->sortable()
                ->hideFromIndex(),

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
