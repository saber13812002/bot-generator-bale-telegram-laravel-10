<?php

namespace App\Nova\Actions;

use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class ApproveBotOwnerPro extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Approve Pro';

    public function handle(ActionFields $fields, Collection $models)
    {
        $service = app(BotOwnerProServiceInterface::class);
        $months = (int) ($fields->months ?? 3);
        $adminId = auth()->id();
        $approved = 0;

        foreach ($models as $model) {
            if ($model->status !== 'pending') {
                continue;
            }

            if ($service->confirmPro($model->id, $adminId, $months)) {
                $approved++;
            }
        }

        return Action::message("{$approved} Pro request(s) approved.");
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Duration', 'months')
                ->options([
                    '3' => '3 months',
                    '6' => '6 months',
                    '12' => '12 months',
                    '0' => 'Unlimited',
                ])
                ->default('3')
                ->rules('required'),
        ];
    }
}
