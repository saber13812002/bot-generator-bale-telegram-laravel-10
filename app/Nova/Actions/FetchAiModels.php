<?php

namespace App\Nova\Actions;

use App\Services\AiProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class FetchAiModels extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = '📋 دریافت لیست مدل‌ها';

    public function handle(ActionFields $fields, Collection $models)
    {
        $results = [];

        foreach ($models as $provider) {
            $service = new AiProviderService($provider);
            $result  = $service->testConnection();

            if ($result['success'] && ! empty($result['models'])) {
                $provider->update([
                    'available_models' => $result['models'],
                    'last_tested_at'   => now(),
                    'last_test_status' => 'success',
                    'last_test_error'  => null,
                    'last_ping_ms'     => $result['ping_ms'],
                ]);

                $list      = implode("\n", array_map(fn ($m, $i) => ($i + 1) . '. ' . $m, $result['models'], array_keys($result['models'])));
                $results[] = "📋 {$provider->name}:\n{$list}";
            } else {
                $results[] = "❌ {$provider->name}: " . ($result['message'] ?? 'خطا');
            }
        }

        return Action::message(implode("\n\n", $results));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
