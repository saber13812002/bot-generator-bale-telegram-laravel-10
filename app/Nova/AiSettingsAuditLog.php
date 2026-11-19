<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * AI-PILOT-ROLLOUT-05 (R1/R2): audit log تغییرات تنظیمات AI (brief §4).
 *
 * هر تغییر aدمینی (stage rollout، allowlist، pro-override، flag هر bot یا
 * سراسری) یک row در این جدول است. read-only — history نباید قابل ویرایش باشد.
 *
 * bot_id خالی = تغییر سراسری (کلیدها با پیشوند ai_flags.*).
 */
class AiSettingsAuditLog extends Resource
{
    public static $model = \App\Models\AiSettingsAuditLog::class;

    public static $title = 'key';

    public static $search = [
        'id',
        'key',
        'bot_id',
        'actor',
    ];

    public static $group = '🤖 AI Platform';

    public static $modelLabel = 'AI Settings Audit';

    public static $displayInNavigation = true;

    public static function label(): string
    {
        return '🧾 AI Settings Audit';
    }

    public static function singularLabel(): string
    {
        return 'AI Setting Change';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('کلید', 'key')
                ->sortable()
                ->help('ai_rollout_stage | ai_test_group | ai_pro_override | ai_feature_flags.* | ai_flags.*'),

            Number::make('Bot ID', 'bot_id')
                ->nullable()
                ->sortable()
                ->help('خالی = تغییر سراسری'),

            Textarea::make('مقدار قبلی', 'old_value')
                ->help('null = قبلاً تعریف نشده بود'),

            Textarea::make('مقدار جدید', 'new_value')
                ->help('null = حذف شد (برگشت به پیش‌فرض)'),

            Text::make('کاربر (actor)', 'actor')
                ->nullable()
                ->sortable(),

            Textarea::make('دلیل (reason)', 'reason')
                ->nullable(),

            DateTime::make('زمان', 'created_at')
                ->sortable(),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false; // read-only history
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false; // read-only history
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }
}
