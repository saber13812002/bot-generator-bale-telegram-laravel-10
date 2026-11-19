<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class ContentSubmissionItem extends Resource
{
    public static $model = \App\Models\ContentSubmissionItem::class;

    public static $title = 'id';

    public static $search = [
        'id',
        'content_text',
        'content_type',
        'status',
    ];

    public static $displayInNavigation = true;

    public static function label()
    {
        return 'آیتم‌های محتوای متنی';
    }

    public static function singularLabel()
    {
        return 'آیتم محتوا';
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Bot', 'bot', Bot::class)
                ->sortable()
                ->rules('required'),

            Number::make('فرستنده (Chat ID)', 'submitter_chat_id')
                ->sortable()
                ->rules('required'),

            Select::make('نوع محتوا', 'content_type')
                ->options([
                    'text' => 'متن',
                    'image' => 'عکس',
                    'video' => 'فیلم',
                ])
                ->displayUsingLabels()
                ->sortable(),

            Textarea::make('متن / Caption', 'content_text')
                ->nullable()
                ->alwaysShow(),

            Text::make('File ID', 'file_id')
                ->nullable()
                ->hideFromIndex(),

            Select::make('وضعیت', 'status')
                ->options([
                    'pending_approval' => 'در انتظار تایید',
                    'approved' => 'تایید شده',
                    'rejected' => 'رد شده',
                    'published' => 'منتشر شده',
                ])
                ->displayUsingLabels()
                ->sortable(),

            Number::make('پیام تایید (گروه)', 'approval_message_id')
                ->nullable()
                ->hideFromIndex(),

            Number::make('تاییدکننده اول', 'first_approver_chat_id')
                ->nullable()
                ->hideFromIndex(),

            Number::make('تاییدکننده دوم', 'second_approver_chat_id')
                ->nullable()
                ->hideFromIndex(),

            DateTime::make('تایید شده در', 'approved_at')
                ->nullable()
                ->sortable(),

            DateTime::make('منتشر شده در', 'published_at')
                ->nullable()
                ->sortable(),

            Number::make('پیام کانال', 'channel_message_id')
                ->nullable()
                ->hideFromIndex(),

            DateTime::make('رد شده در', 'rejected_at')
                ->nullable()
                ->hideFromIndex(),

            Textarea::make('دلیل رد', 'rejection_reason')
                ->nullable()
                ->hideFromIndex(),

            DateTime::make('ایجاد', 'created_at')
                ->nullable()
                ->sortable()
                ->exceptOnForms(),
        ];
    }

    public function cards(NovaRequest $request)
    {
        return [];
    }

    public function filters(NovaRequest $request)
    {
        return [];
    }

    public function actions(NovaRequest $request)
    {
        return [];
    }
}
