<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — token usage هر attempt موفق (brief §6).
 *
 * ProcessAiJob مقدار usage برگشتی provider (OpenAI-compatible
 * usage: {prompt_tokens, completion_tokens, total_tokens}) را اینجا ذخیره
 * می‌کند؛ مبنای token usage و est. cost در metrics/stage-report.
 * null = attempt ناموفق یا provider بدون گزارش usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->json('usage')->nullable()->after('result_reference');
        });
    }

    public function down(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->dropColumn('usage');
        });
    }
};
