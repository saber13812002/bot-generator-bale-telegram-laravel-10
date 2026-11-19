<?php

namespace App\Nova;

use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class ChannelPosterQueue extends Resource
{
    public static $model = \App\Models\ChannelPosterQueue::class;

    public static $title = 'id';

    public static $search = [
        'id',
        'tag',
        'status',
        'owner_chat_id',
    ];

    public static function label(): string
    {
        return '📤 Channel Poster Queue';
    }

    public static function singularLabel(): string
    {
        return 'Channel Poster Queue Item';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Bot ID', 'bot_id')
                ->sortable(),

            Text::make('تگ', 'tag')
                ->nullable()
                ->sortable(),

            Text::make('نوع محتوا', 'content_type')
                ->nullable()
                ->sortable(),

            Textarea::make('متن', 'text')
                ->nullable()
                ->hideFromIndex()
                ->alwaysShow(),

            Text::make('File ID', 'file_id')
                ->nullable()
                ->hideFromIndex(),

            Boolean::make('امضا فعال', 'signature_enabled')
                ->default(false)
                ->hideFromIndex(),

            Badge::make('وضعیت', 'status')
                ->map([
                    'pending'   => 'warning',
                    'published' => 'success',
                    'failed'    => 'danger',
                    'cancelled' => 'grey',
                ])
                ->sortable(),

            DateTime::make('زمان مقرر', 'scheduled_at')
                ->nullable()
                ->readonly()
                ->sortable(),

            DateTime::make('زمان انتشار', 'published_at')
                ->nullable()
                ->readonly(),

            Text::make('مالک (Chat ID)', 'owner_chat_id')
                ->nullable()
                ->hideFromIndex(),

            Text::make('پلتفرم مالک', 'owner_origin')
                ->nullable()
                ->hideFromIndex(),
        ];
    }
}
