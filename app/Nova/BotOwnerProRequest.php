<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\ApproveBotOwnerPro;
use App\Nova\Actions\RejectBotOwnerPro;

class BotOwnerProRequest extends Resource
{
    public static $model = \App\Modules\BotOwner\Models\BotOwnerProRequest::class;

    public static $title = 'id';

    public static $search = ['id'];

    public static function label(): string
    {
        return 'Bot Owner Pro Requests';
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Bot Owner', 'botOwner', BotOwner::class)->sortable(),
            Select::make('Status', 'status')->options([
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'rejected' => 'Rejected',
            ])->displayUsingLabels()->readonly(),
            Textarea::make('Notes', 'notes')->nullable(),
            DateTime::make('Approved At', 'approved_at')->nullable()->readonly(),
            DateTime::make('Created At', 'created_at')->exceptOnForms(),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            new ApproveBotOwnerPro,
            new RejectBotOwnerPro,
        ];
    }
}
