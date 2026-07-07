<?php

namespace App\Nova\Actions;

use App\Models\BotAdminKieRequest;
use App\Services\BotAdminKieService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ConfirmBotAdminKieRequest extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = '✅ تأیید درخواست ادمین';

    public function handle(ActionFields $fields, Collection $models)
    {
        $service = app(BotAdminKieService::class);
        $adminChatId = (string) (auth()->user()->chat_id ?? auth()->id());
        $confirmed = 0;
        $errors = [];

        foreach ($models as $request) {
            if ($request->status !== 'pending') {
                $errors[] = "Request #{$request->id}: وضعیت '" . $request->status . "' است (فقط pending قابل تأیید).";
                continue;
            }

            try {
                $result = $service->confirmRequest((int) $request->id, (int) $adminChatId);
                if ($result) {
                    $confirmed++;
                } else {
                    $errors[] = "Request #{$request->id}: درخواست یافت نشد یا پردازش نشد.";
                }
            } catch (\Throwable $e) {
                $errors[] = "Request #{$request->id}: خطا — " . $e->getMessage();
            }
        }

        $message = "✅ {$confirmed} درخواست تأیید شد.";
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
