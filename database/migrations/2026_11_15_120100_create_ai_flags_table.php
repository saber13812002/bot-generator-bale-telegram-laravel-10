<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-PILOT-ROLLOUT-05 (R2): flagهای سراسری AI — override بدون re-deploy.
 *
 * brief §4: «تغییر Feature flag نباید نیاز به re-deploy داشته باشد».
 * این جدول override اجرایی برای config('ai.flags.*') است:
 *  - ai_global_enabled        — AI Global Kill Switch (کل همه‌چیز)
 *  - ai_llm/ocr/asr/rag/tts/growth_companion_enabled — flag هر capability
 *
 * نبود row برای یک کلید = مقدار env/config حاکم است.
 * تغییر هر row در ai_settings_audit_logs ثبت می‌شود (bot_id NULL = سراسری).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->boolean('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_flags');
    }
};
