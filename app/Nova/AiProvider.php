<?php

namespace App\Nova;

use App\Nova\Actions\FetchAiModels;
use App\Nova\Actions\SendTestChat;
use App\Nova\Actions\TestAiConnection;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class AiProvider extends Resource
{
    public static $model = \App\Models\AiProvider::class;

    public static $title = 'name';

    public static $search = [
        'id',
        'name',
        'base_url',
        'model_name',
    ];

    public static $group = '⚙️ تنظیمات';

    public static function label(): string
    {
        return '🤖 AI Providers';
    }

    public static function singularLabel(): string
    {
        return 'AI Provider';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('نام', 'name')
                ->rules('required', 'max:255')
                ->sortable(),

            Text::make('آدرس API', 'base_url')
                ->rules('required', 'url')
                ->sortable(),

            Text::make('API Key', 'api_key')
                ->onlyOnForms()
                ->rules('required'),

            Text::make('مدل پیش‌فرض', 'model_name')
                ->nullable()
                ->sortable(),

            Textarea::make('پرامپت پیش‌فرض', 'default_prompt')
                ->nullable()
                ->alwaysShow(),

            Select::make('نوع سرویس', 'provider_type')
                ->options([
                    'openai_compatible' => 'OpenAI Compatible',
                    'custom'            => 'Custom',
                ])
                ->default('openai_compatible')
                ->sortable(),

            Boolean::make('فعال', 'is_active')
                ->default(true)
                ->sortable(),

            // --- نوتیفیکیشن ---
            Text::make('توکن ربات نوتیفیکیشن', 'notify_bot_token')
                ->nullable()
                ->hideFromIndex(),

            Text::make('چت آیدی نوتیفیکیشن', 'notify_chat_id')
                ->nullable()
                ->hideFromIndex(),

            Select::make('پلتفرم نوتیفیکیشن', 'notify_platform')
                ->options([
                    'bale'     => 'بله',
                    'telegram' => 'تلگرام',
                ])
                ->default('bale')
                ->hideFromIndex(),

            // --- وضعیت Health ---
            DateTime::make('آخرین تست', 'last_tested_at')
                ->readonly()
                ->sortable(),

            Badge::make('وضعیت', 'last_test_status')
                ->map([
                    'success' => 'success',
                    'failed'  => 'danger',
                ])
                ->sortable(),

            Number::make('پینگ (ms)', 'last_ping_ms')
                ->readonly()
                ->sortable(),

            Code::make('مدل‌های موجود', 'available_models')
                ->json()
                ->readonly()
                ->hideFromIndex(),

            Textarea::make('آخرین خطا', 'last_test_error')
                ->readonly()
                ->hideFromIndex()
                ->alwaysShow(),

            Code::make('تنظیمات اضافی', 'settings')
                ->json()
                ->nullable()
                ->hideFromIndex(),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            new TestAiConnection(),
            new FetchAiModels(),
            new SendTestChat(),
        ];
    }

    public function cards(NovaRequest $request): array
    {
        return [];
    }

    public function filters(NovaRequest $request): array
    {
        return [];
    }

    public function lenses(NovaRequest $request): array
    {
        return [];
    }
}
