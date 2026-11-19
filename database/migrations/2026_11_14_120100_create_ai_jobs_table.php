<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ردیابی کسب‌وکار کارهای AI (WS-D) — مستقل از جدول فنی jobs.
 *
 * ستون‌های الزامی master prompt:
 * job_id, bot_id, customer_id, user_id, feature, provider, model,
 * priority, state, attempt_number, created_at, started_at, completed_at,
 * error_code, result_reference, idempotency_key
 *
 * + ستون‌های تاب‌آوری: due_at (تأخیر/DEFERRED), expires_at,
 * heartbeat_at (recovery از worker مرده), locked_by (concurrency per-bot).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 64)->unique();             // uuid job در صف فنی
            $table->string('idempotency_key', 128)->unique();   // جلوگیری از دوباره‌کاری
            $table->unsignedBigInteger('bot_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('feature', 64)->index();             // llm_chat|ocr|asr|tts|...
            $table->string('priority', 32)->default('regular'); // pro|regular|deferred|low
            $table->string('state', 32)->default('PENDING')->index();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(0);
            $table->timestamp('due_at')->nullable();            // زمانی که قابل پردازش شود
            $table->timestamp('expires_at')->nullable();        // بعد از این EXPIRED
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->string('locked_by', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('result_reference')->nullable();       // id رکورد نتیجه (e.g. ai_llms.id)
            $table->json('payload')->nullable();
            $table->timestamps();

            // flush deferred: انتخاب کارهای قابل اجرا به ترتیب اولویت
            $table->index(['state', 'due_at', 'priority', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};
