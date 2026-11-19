<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-PILOT-ROLLOUT-05 (R1): audit log تغییرات تنظیمات AI مدیریت‌شده.
 *
 * brief §4: «هر تغییر ادمین نیاز به Audit Log دارد». این جدول هر تغییر
 * کلیدهای مدیریت‌شده در bots.ai_settings را ثبت می‌کند:
 *  - ai_rollout_stage       (stage rollout: 0 internal, 1 pilot, 2 pro, 3 public)
 *  - ai_test_group          (allowlist pilot)
 *  - ai_pro_override        (حذف محدودیت plan برای customerهای آزمایشی — ثبت‌شده)
 *  - ai_feature_flags.*     (flag هر capability — مستقل، بدون نیاز به re-deploy)
 *
 * bot_id NULL = تغییر سراسری (R2: flags global در جدول ai_flags).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->nullable()->index();
            $table->string('key', 191);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('actor', 255)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings_audit_logs');
    }
};
