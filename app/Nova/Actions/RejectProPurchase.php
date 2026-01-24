<?php

namespace App\Nova\Actions;

use App\Models\ProPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Support\Facades\Log;

class RejectProPurchase extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'رد درخواست Pro';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $adminId = auth()->id();

        foreach ($models as $request) {
            if ($request->status !== 'pending') {
                continue;
            }

            $request->status = 'rejected';
            $request->admin_notes = $fields->rejection_reason ?? 'Rejected by admin';
            $request->save();

            Log::info('❌ [Nova] Pro purchase rejected', [
                'request_id' => $request->id,
                'admin_id' => $adminId,
            ]);
        }

        return Action::message('درخواست‌ها رد شدند');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('دلیل رد', 'rejection_reason')
                ->nullable()
                ->help('دلیل رد درخواست را وارد کنید'),
        ];
    }
}
