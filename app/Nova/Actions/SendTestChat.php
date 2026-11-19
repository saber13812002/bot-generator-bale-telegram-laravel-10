<?php

namespace App\Nova\Actions;

use App\Services\AiProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class SendTestChat extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = '💬 تست چت';

    public function handle(ActionFields $fields, Collection $models)
    {
        $prompt  = $fields->get('prompt');
        $results = [];

        foreach ($models as $provider) {
            $service    = new AiProviderService($provider);
            $chatResult = $service->chat($prompt ?: null);

            if ($chatResult['success']) {
                $response  = $chatResult['response'];
                $charCount = mb_strlen($response);
                $usage     = $chatResult['usage'];
                $tokens    = ($usage['total_tokens'] ?? '?');
                $results[] = "💬 {$provider->name}:\n\"{$response}\" ({$charCount} کاراکتر, {$tokens} tokens)";
            } else {
                $results[] = "❌ {$provider->name}: {$chatResult['response']}";
            }
        }

        return Action::message(implode("\n\n", $results));
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Textarea::make('پرامپت (خالی = پیش‌فرض)', 'prompt')
                ->nullable()
                ->help('اگر خالی بگذارید از پرامپت پیش‌فرض provider استفاده می‌شود'),
        ];
    }
}
