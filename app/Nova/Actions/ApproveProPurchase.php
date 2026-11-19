<?php

namespace App\Nova\Actions;

use App\Services\ProServiceImpl;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Support\Facades\Log;

class ApproveProPurchase extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'تایید درخواست Pro';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $proService = app(ProServiceImpl::class);
        $adminId = auth()->id();

        foreach ($models as $request) {
            if ($request->status !== 'pending') {
                continue;
            }

            $result = $proService->confirmPurchase($request->id, $adminId);

            if ($result) {
                Log::info('✅ [Nova] Pro purchase approved', [
                    'request_id' => $request->id,
                    'admin_id' => $adminId,
                ]);
            }
        }

        return Action::message('درخواست‌ها با موفقیت تایید شدند');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
