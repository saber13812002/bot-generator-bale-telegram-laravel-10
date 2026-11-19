<?php

namespace App\Nova\Actions;

use App\Models\BotAdminKieRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class RejectBotAdminKieRequest extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = '❌ رد درخواست ادمین';

    public function handle(ActionFields $fields, Collection $models)
    {
        $adminChatId = auth()->user()->chat_id ?? auth()->id();
        $rejected = 0;
        $errors = [];

        foreach ($models as $request) {
            if ($request->status !== 'pending') {
                $errors[] = "Request #{$request->id}: وضعیت '" . $request->status . "' است (فقط pending قابل رد).";
                continue;
            }

            $request->update([
                'status' => 'rejected',
                'approved_by' => $adminChatId,
                'approved_at' => now(),
            ]);
            $rejected++;
        }

        $message = "❌ {$rejected} درخواست رد شد.";
        if (!empty($errors)) {
            $message .= "\n\n⚠️ خطاها:\n" . implode("\n", $errors);
        }

        return Action::message($message);
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
