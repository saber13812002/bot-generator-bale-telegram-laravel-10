<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class ContentAsset extends Resource
{
    public static $model = \App\Models\ContentAsset::class;

    public static $title = 'content_url';

    public static $search = [
        'id', 'content_url',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Content Item', 'item', ContentItem::class)
                ->searchable()
                ->sortable()
                ->help('آیتم محتوایی مرتبط'),

            Select::make('Type', 'type')
                ->options([
                    'audio' => '🎵 Audio',
                    'voice' => '🎤 Voice',
                    'pdf' => '📄 PDF',
                    'infographic' => '🖼 Infographic',
                ])
                ->displayUsingLabels()
                ->sortable()
                ->rules('required')
                ->help('نوع فایل'),

            Text::make('Content URL', 'content_url')
                ->nullable()
                ->help('URL محتوا (در صورت وجود)'),

            Text::make('Telegram File ID', 'telegram_file_id')
                ->nullable()
                ->hideFromIndex()
                ->help('File ID در تلگرام'),

            Text::make('Bale File ID', 'bale_file_id')
                ->nullable()
                ->hideFromIndex()
                ->help('File ID در بله'),

            \Laravel\Nova\Fields\DateTime::make('Created', 'created_at')
                ->onlyOnDetail(),
        ];
    }

    public static function label(): string
    {
        return '🎬 Content Assets';
    }

    public static function singularLabel(): string
    {
        return 'Asset';
    }
}
