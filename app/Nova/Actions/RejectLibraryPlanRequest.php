<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class RejectLibraryPlanRequest extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'رد درخواست پلن';

    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $request) {
            if ($request->status === 'pending') {
                $request->update(['status' => 'rejected']);
            }
        }

        return Action::message('درخواست‌ها رد شدند');
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
