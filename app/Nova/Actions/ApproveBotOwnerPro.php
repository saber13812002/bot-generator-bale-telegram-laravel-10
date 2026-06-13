<?php

namespace App\Nova\Actions;

use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class ApproveBotOwnerPro extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Approve Pro';

    public function handle(ActionFields $fields, Collection $models)
    {
        $service = app(BotOwnerProServiceInterface::class);

        foreach ($models as $model) {
            $service->confirmPro($model->id, auth()->id());
        }

        return Action::message('Pro requests approved.');
    }
}
