<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;

class AiProviderLog extends Resource
{
    public static $model = \App\Models\AiProviderLog::class;
    public static $title = 'id';
    public static $search = ['id', 'check_type', 'status', 'error_message'];
    public static $displayInNavigation = false;

    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Provider', 'provider', AiProvider::class)
                ->sortable(),

            Text::make('Check Type', 'check_type')
                ->sortable(),

            Badge::make('Status')
                ->map([
                    'success' => 'success',
                    'failed'  => 'danger',
                ])->sortable(),

            Number::make('Ping (ms)', 'ping_ms')
                ->sortable(),

            Code::make('Request Payload', 'request_payload')
                ->json(),

            Code::make('Response Payload', 'response_payload')
                ->json(),

            Text::make('Error Message', 'error_message')
                ->hideFromIndex(),

            DateTime::make('Created At', 'created_at')
                ->sortable(),
        ];
    }

    public function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    public function cards(Request $request)
    {
        return [];
    }

    public function filters(Request $request)
    {
        return [];
    }

    public function lenses(Request $request)
    {
        return [];
    }

    public function actions(Request $request)
    {
        return [];
    }
}
