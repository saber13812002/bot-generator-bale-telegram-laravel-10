<?php

namespace App\Nova;

use App\Nova\Actions\ApproveLibraryPlanRequest;
use App\Nova\Actions\RejectLibraryPlanRequest;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class LibraryPlanRequest extends Resource
{
    public static $model = \App\Models\LibraryPlanRequest::class;

    public static $title = 'id';

    public static $search = ['id', 'user_identifier', 'plan'];

    public static function label()
    {
        return 'Library Plan Requests';
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Bot User', 'botUser', BotUsers::class),
            BelongsTo::make('Bot', 'bot', Bot::class),
            Text::make('Plan', 'plan'),
            Text::make('User Identifier', 'user_identifier'),
            Select::make('Status', 'status')->options([
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'rejected' => 'Rejected',
            ])->displayUsingLabels(),
            Textarea::make('Payment Info', 'payment_info')->nullable(),
            Textarea::make('Admin Notes', 'admin_notes')->nullable(),
            DateTime::make('Approved At', 'approved_at')->nullable(),
            DateTime::make('Created At', 'created_at')->readonly(),
        ];
    }

    public function actions(NovaRequest $request)
    {
        return [
            new ApproveLibraryPlanRequest(),
            new RejectLibraryPlanRequest(),
        ];
    }
}
