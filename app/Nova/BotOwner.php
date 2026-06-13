<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class BotOwner extends Resource
{
    public static $model = \App\Modules\BotOwner\Models\BotOwner::class;

    public static $title = 'phone';

    public static $search = ['id', 'phone', 'name'];

    public static function label(): string
    {
        return 'Bot Owners';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Phone', 'phone')->sortable(),
            Text::make('Name', 'name')->nullable(),
            Text::make('Bale Chat ID', 'bale_chat_id')->nullable(),
            Boolean::make('Is Pro', 'is_pro'),
            DateTime::make('Pro Confirmed At', 'pro_confirmed_at')->nullable(),
            Text::make('Status', 'status'),
            DateTime::make('Last Login', 'last_login_at')->nullable(),
        ];
    }
}
