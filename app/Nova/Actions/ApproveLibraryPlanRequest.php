<?php

namespace App\Nova\Actions;

use App\Services\BookLibraryPlanServiceImpl;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ApproveLibraryPlanRequest extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'تایید پلن کتابخانه';

    public function handle(ActionFields $fields, Collection $models)
    {
        $service = app(BookLibraryPlanServiceImpl::class);
        $admin = (string) (auth()->id() ?? 'nova_admin');

        foreach ($models as $request) {
            if ($request->status !== 'pending') {
                continue;
            }
            $service->confirmPlanRequest($request->id, $admin);
        }

        return Action::message('درخواست‌ها تایید شدند');
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
