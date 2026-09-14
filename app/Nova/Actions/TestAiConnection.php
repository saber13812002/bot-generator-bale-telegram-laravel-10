<?php

namespace App\Nova\Actions;

use App\Models\AiProvider;
use App\Services\AiProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class TestAiConnection extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = '🔌 تست اتصال';

    public function handle(ActionFields $fields, Collection $models)
    {
        $results = [];

        foreach ($models as $provider) {
            $service = new AiProviderService($provider);
            $result  = $service->testConnection();

            $provider->update([
                'last_tested_at'   => now(),
                'last_test_status' => $result['success'] ? 'success' : 'failed',
                'last_test_error'  => $result['success'] ? null : $result['message'],
                'last_ping_ms'     => $result['ping_ms'],
            ]);

            if ($result['success']) {
                $modelsList = implode(', ', $result['models']);
                $results[]  = "✅ {$provider->name}: OK — ping {$result['ping_ms']}ms — models: {$modelsList}";
            } else {
                $results[] = "❌ {$provider->name}: {$result['message']}";
            }
        }

        return Action::message(implode("\n", $results));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
