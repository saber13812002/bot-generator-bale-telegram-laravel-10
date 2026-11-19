<?php

namespace App\Nova;

use App\Models\AiSettingsAuditLog;
use App\Services\Ai\AiFlagService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * AI-PILOT-ROLLOUT-05 (R2): Feature flags بدون re-deploy (brief §4).
 *
 * هر row یک override سراسری برای config('ai.flags.*') است:
 *  - ai_global_enabled — AI Global Kill Switch (کل همه‌چیز)
 *  - ai_{capability}_enabled — flag مستقل هر capability
 *
 * ویرایش این resource بدون deploy اعمال می‌شود (AiFeatureGate در L0.5/L1
 * override را می‌خواند) و هر تغییرش audit log دارد.
 *
 * نکته: اگر برای یک کلید row نباشد، مقدار env/config حاکم است — row را
 * حذف کنید تا به حالت پیش‌فرض برگردد (آیکون سبد/نمای index را ببینید).
 */
class AiFlag extends Resource
{
    public static $model = \App\Models\AiFlag::class;

    public static $title = 'key';

    public static $displayInNavigation = true;

    public static $search = [
        'id',
        'key',
    ];

    public static $group = '🤖 AI Platform';

    public static $modelLabel = 'AI Flags';

    public static function label(): string
    {
        return '🎛️ AI Feature Flags';
    }

    public static function singularLabel(): string
    {
        return 'AI Flag';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Select::make('کلید (key)', 'key')
                ->options($this->flagOptions())
                ->rules('required')
                ->help('کلید flag از config/ai.php. row بدون کلید = config حاکم است.'),

            Boolean::make('مقدار (value)', 'value')
                ->help('true/false = override فعال. برای حذف override و برگشت به env، این row را حذف کنید.'),
        ];
    }

    /**
     * Nova 4 lifecycle hooks (HasLifecycleMethods): audit هر تغییر (brief §4).
     * actor = ادمین Nova که صفحه را ذخیره کرده است.
     */
    public static function afterCreate(NovaRequest $request, Model $model): void
    {
        self::audit($request, "ai_flags.{$model->key}", null, $model->value);
    }

    public static function afterUpdate(NovaRequest $request, Model $model): void
    {
        // getOriginal = مقدار قبل از save() (Eloquent) — نیاز به query دوم نیست
        $old = $model->getOriginal('value');
        self::audit($request, "ai_flags.{$model->key}", $old, $model->value);
    }

    public static function afterDelete(NovaRequest $request, Model $model): void
    {
        self::audit($request, "ai_flags.{$model->key}", $model->value, null);
    }

    private static function audit(NovaRequest $request, string $key, ?bool $old, ?bool $new): void
    {
        if ($old === $new) {
            return;
        }

        try {
            $actor = $request->user()?->name ?? $request->user()?->email ?? 'nova-admin';

            AiSettingsAuditLog::create([
                'bot_id'    => null, // تغییر سراسری (نه bot خاص)
                'key'       => $key,
                'old_value' => $old === null ? null : ($old ? 'true' : 'false'),
                'new_value' => $new === null ? null : ($new ? 'true' : 'false'),
                'actor'     => $actor,
                'reason'    => 'Nova: ویرایش مستقیم override flag',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Nova AiFlag: نوشتن audit log شکست', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function flagOptions(): array
    {
        $labels = [
            'ai_global_enabled'           => '🔴 GLOBAL KILL SWITCH (ai_global_enabled)',
            'ai_llm_enabled'              => 'LLM (ai_llm_enabled)',
            'ai_ocr_enabled'              => 'OCR (ai_ocr_enabled)',
            'ai_asr_enabled'              => 'ASR (ai_asr_enabled)',
            'ai_rag_enabled'              => 'RAG (ai_rag_enabled)',
            'ai_tts_enabled'              => 'TTS (ai_tts_enabled)',
            'ai_growth_companion_enabled' => 'Growth Companion (ai_growth_companion_enabled)',
        ];

        // فقط کلیدهای شناخته‌شده (محافظت از typo)
        return array_intersect_key($labels, array_flip(AiFlagService::knownKeys()));
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return true;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return true;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return true;
    }
}
