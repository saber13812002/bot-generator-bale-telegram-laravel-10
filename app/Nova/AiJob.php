<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * AI-PILOT-ROLLOUT-05 (R2): نمایش jobهای AI (brief §4: jobs view / errors view).
 *
 * read-only: jobها از صف AI مدیریت می‌شوند (ai:queue-status و commands)؛
 * پنل فقط مشاهده/فیلتر برای عیب‌یابی است.
 */
class AiJob extends Resource
{
    public static $model = \App\Models\AiJob::class;

    public static $title = 'job_id';

    public static $search = [
        'id',
        'job_id',
        'feature',
        'bot_id',
        'state',
    ];

    public static $group = '🤖 AI Platform';

    public static $modelLabel = 'AI Jobs';

    public static function label(): string
    {
        return '📋 AI Jobs';
    }

    public static function singularLabel(): string
    {
        return 'AI Job';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Job ID', 'job_id')
                ->copyable()
                ->sortable(),

            Text::make('Feature', 'feature')
                ->sortable()
                ->help('capability: llm|ocr|asr|rag|tts|growth_companion'),

            Number::make('Bot ID', 'bot_id')
                ->sortable(),

            Number::make('Customer ID', 'customer_id')
                ->nullable()
                ->sortable(),

            Number::make('User ID', 'user_id')
                ->nullable(),

            Badge::make('State', 'state')
                ->map([
                    'PENDING'    => 'warning',
                    'QUEUED'     => 'warning',
                    'PROCESSING' => 'info',
                    'RETRYING'   => 'warning',
                    'DEFERRED'   => 'info',
                    'COMPLETED'  => 'success',
                    'FAILED'     => 'danger',
                    'CANCELLED'  => 'danger',
                    'EXPIRED'    => 'danger',
                ])
                ->sortable(),

            Text::make('Priority', 'priority')
                ->sortable(),

            Number::make('Attempts', 'attempt_number')
                ->sortable()
                ->help('سقف max_attempts بعد → FAILED (dead letter)'),

            Text::make('Error Code', 'error_code')
                ->sortable()
                ->help('auth_failed|model_not_found|rate_limited|provider_unavailable|provider_error|...'),

            Text::make('Provider', 'provider')
                ->nullable(),

            Text::make('Model', 'model')
                ->nullable(),

            DateTime::make('Due At', 'due_at')
                ->nullable(),

            DateTime::make('Started At', 'started_at')
                ->nullable(),

            DateTime::make('Completed At', 'completed_at')
                ->sortable(),

            Text::make('Result Ref', 'result_reference')
                ->nullable()
                ->hideFromIndex(),

            Text::make('Created At', 'created_at')
                ->sortable(),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false; // read-only
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false; // read-only
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false; // read-only
    }
}
