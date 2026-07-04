<?php

namespace App\Nova;

use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class IdeaMessage extends Resource
{
    public static $model = \App\Models\IdeaMessage::class;

    public static $title = 'message';

    public static $search = ['message'];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Idea ID', 'idea_id')->sortable(),
            Text::make('Sender', 'sender_type')->sortable(),
            Text::make('Sender Name', 'sender_name')->nullable(),
            Textarea::make('Message', 'message')->alwaysShow(),
            DateTime::make('Sent', 'created_at')->onlyOnDetail(),
        ];
    }

    public static function label(): string
    {
        return '💬 Messages';
    }
}
