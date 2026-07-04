<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class Idea extends Resource
{
    public static $model = \App\Models\Idea::class;

    public static $title = 'title';

    public static $search = [
        'id', 'title', 'description', 'submitter_name',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Endpoint', 'endpoint_id')
                ->sortable()
                ->hideFromIndex()
                ->help('رباط مرتبط با ایده'),

            Text::make('Title', 'title')
                ->sortable()
                ->rules('required', 'max:255'),

            Textarea::make('Description', 'description')
                ->alwaysShow()
                ->rules('required'),

            Text::make('Submitter', 'submitter_name')
                ->hideFromIndex()
                ->nullable(),

            Text::make('Contact', 'submitter_contact')
                ->hideFromIndex()
                ->nullable(),

            Badge::make('Status', 'status')
                ->map([
                    'pending' => 'warning',
                    'reviewing' => 'info',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'done' => 'success',
                ])
                ->sortable(),

            Select::make('Status', 'status')
                ->options([
                    'pending' => 'Pending',
                    'reviewing' => 'Reviewing',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'done' => 'Done',
                ])
                ->onlyOnForms()
                ->default('pending'),

            Textarea::make('Admin Note', 'admin_note')
                ->alwaysShow()
                ->nullable()
                ->help('یادداشت داخلی ادمین'),

            DateTime::make('Created At', 'created_at')
                ->onlyOnDetail(),

            DateTime::make('Reviewed At', 'reviewed_at')
                ->onlyOnDetail()
                ->nullable(),
        ];
    }

    public static function label(): string
    {
        return '💡 Ideas';
    }

    public static function singularLabel(): string
    {
        return 'Idea';
    }
}
