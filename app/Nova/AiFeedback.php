<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * AI-PILOT-ROLLOUT-05 (R3): بازخورد customerهای pilot (brief §8).
 *
 * read-only در پنل (feedback از ربات/فرم می‌آید)؛ aدمین وضعیت آن را
 * follow-up می‌کند (new → in_progress → resolved/ignored).
 */
class AiFeedback extends Resource
{
    public static $model = \App\Models\AiFeedback::class;

    public static $title = 'comment';

    public static $displayInNavigation = true;

    public static $search = [
        'id',
        'feature',
        'category',
        'status',
    ];

    public static $group = '🤖 AI Platform';

    public static $modelLabel = 'AI Feedback';

    public static function label(): string
    {
        return '💬 AI Feedback (Pilot)';
    }

    public static function singularLabel(): string
    {
        return 'AI Feedback';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Number::make('Bot ID', 'bot_id')
                ->nullable()
                ->sortable(),

            Number::make('Customer ID', 'customer_id')
                ->nullable()
                ->sortable(),

            Text::make('Feature', 'feature')
                ->sortable()
                ->help('capability مربوط به بازخورد (llm_chat|ocr|asr|... یا general)'),

            Badge::make('Category', 'category')
                ->map([
                    'bug'     => 'danger',
                    'quality' => 'warning',
                    'missing' => 'info',
                    'general' => 'success',
                    'other'   => 'warning',
                ])
                ->sortable(),

            Textarea::make('Comment', 'comment')
                ->nullable(),

            Number::make('Rating (1-5)', 'rating')
                ->nullable(),

            Badge::make('Status', 'status')
                ->map([
                    'new'         => 'warning',
                    'in_progress' => 'info',
                    'resolved'    => 'success',
                    'ignored'     => 'danger',
                ])
                ->sortable(),

            Text::make('Resolved By', 'resolved_by')
                ->nullable(),

            DateTime::make('Resolved At', 'resolved_at')
                ->nullable(),

            DateTime::make('Created At', 'created_at')
                ->sortable(),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false; // read-only (feedback از ربات/فرم می‌آید)
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
