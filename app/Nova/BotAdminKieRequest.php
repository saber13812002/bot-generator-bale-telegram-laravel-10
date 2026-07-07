<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class BotAdminKieRequest extends Resource
{
    public static $model = \App\Models\BotAdminKieRequest::class;

    public static $title = 'id';

    public static $search = ['id', 'chat_id', 'first_name', 'last_name', 'username'];

    public static function label()
    {
        return '🔐 Admin Requests';
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Bot', 'bot', \App\Nova\Bot::class)->nullable(),

            Text::make('Chat ID', 'chat_id'),
            Text::make('Origin', 'origin'),
            Text::make('Name', function () {
                return $this->resource->displayName();
            })->onlyOnIndex(),

            Text::make('First Name', 'first_name')->hideFromIndex(),
            Text::make('Last Name', 'last_name')->hideFromIndex(),
            Text::make('Username', 'username')->hideFromIndex(),
            Text::make('Alias', 'alias_name')->hideFromIndex(),
            Text::make('Email', 'email')->hideFromIndex(),

            Text::make('Endpoint', 'webhook_endpoint')->hideFromIndex(),

            Select::make('Status', 'status')->options([
                'pending' => '🟡 Pending',
                'confirmed' => '🟢 Confirmed',
                'rejected' => '🔴 Rejected',
            ])->displayUsingLabels(),

            Text::make('Approved By', 'approved_by')->nullable(),
            DateTime::make('Approved At', 'approved_at')->nullable(),

            DateTime::make('Created', 'created_at')->onlyOnDetail(),
        ];
    }

    public function actions(NovaRequest $request)
    {
        return [
            new \App\Nova\Actions\ConfirmBotAdminKieRequest(),
            new \App\Nova\Actions\RejectBotAdminKieRequest(),
        ];
    }
}
