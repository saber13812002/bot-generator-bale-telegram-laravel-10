<?php

namespace Tests;

use App\Models\AiProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * میزبانی جدول ai_providers روی SQLite حافظه‌ای برای تست‌هایی که
 * به یک provider LLM فعال نیاز دارند (LlmTranslationService،
 * ActivityReportSummaryService، ChannelMotivationSend).
 *
 * همراه با UsesChannelPosterSqlite استفاده می‌شود (آن trait اتصال
 * sqlite :memory: را در createApplication فعال می‌کند).
 */
trait UsesAiProviderSqlite
{
    protected function setUpAiProvidersTable(): void
    {
        Schema::dropIfExists('ai_providers');

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->text('api_key');
            $table->string('model_name')->nullable();
            $table->text('default_prompt')->nullable();
            $table->string('provider_type')->default('openai_compatible');
            $table->boolean('is_active')->default(true);
            $table->string('notify_bot_token')->nullable();
            $table->string('notify_chat_id')->nullable();
            $table->string('notify_platform')->nullable()->default('bale');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_error')->nullable();
            $table->integer('last_ping_ms')->nullable();
            $table->json('available_models')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    protected function createActiveAiProvider(): AiProvider
    {
        return AiProvider::create([
            'name' => 'Test LLM',
            'base_url' => 'http://llm.test/api',
            'api_key' => 'sk-test',
            'model_name' => 'qwen38',
            'is_active' => true,
        ]);
    }
}
