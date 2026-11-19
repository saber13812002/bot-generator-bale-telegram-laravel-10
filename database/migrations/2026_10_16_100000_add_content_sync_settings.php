<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تنظیمات جدید همگام‌سازی چندکاناله (Content Sync — phase 2)
 *
 * - content_sync_admin_prefs: ستون‌های dedup_mode (حذف کانال مبدأ vs حذف کل پیام‌رسان)
 *   و fast_mode (به‌خاطر سپردن تگ/روش از عملیات‌های قبلی)
 * - content_sync_bot_defaults: پیش‌فرض مشترک کل ربات (تنها مالک می‌تواند تنظیم کند) —
 *   برای هر ادمینی که هنوز تنظیم شخصی نکرده باشد اعمال می‌شود.
 *
 * مهاجرت محافظت‌شده: اگر ستون/جدول قبلاً وجود داشته باشد، دست نمی‌خورد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_sync_admin_prefs')) {
            if (!Schema::hasColumn('content_sync_admin_prefs', 'dedup_mode')) {
                Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                    // exclude_source_channel (default) | exclude_source_platform
                    $table->string('dedup_mode', 30)->default('exclude_source_channel')->after('publish_mode');
                });
            }

            if (!Schema::hasColumn('content_sync_admin_prefs', 'fast_mode')) {
                Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                    $table->boolean('fast_mode')->default(true)->after('dedup_mode');
                });
            }

            if (!Schema::hasColumn('content_sync_admin_prefs', 'settings_asked')) {
                Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                    // سؤال یک‌باره‌ی تنظیمات اولیه — فقط یک‌بار نشان داده می‌شود
                    $table->boolean('settings_asked')->default(false)->after('fast_mode');
                });
            }
        }

        if (!Schema::hasTable('content_sync_bot_defaults')) {
            Schema::create('content_sync_bot_defaults', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bot_id');
                $table->string('origin', 20)->default('bale');
                $table->string('set_by_chat_id', 64)->nullable();
                // immediate | queue
                $table->string('publish_mode', 20)->default('immediate');
                // exclude_source_channel | exclude_source_platform
                $table->string('dedup_mode', 30)->default('exclude_source_channel');
                $table->boolean('fast_mode')->default(true);
                $table->timestamps();

                $table->unique(['bot_id', 'origin'], 'cs_bot_defaults_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_sync_bot_defaults');

        if (Schema::hasTable('content_sync_admin_prefs')) {
            if (Schema::hasColumn('content_sync_admin_prefs', 'settings_asked')) {
                Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                    $table->dropColumn('settings_asked');
                });
            }
            if (Schema::hasColumn('content_sync_admin_prefs', 'fast_mode')) {
                Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                    $table->dropColumn('fast_mode');
                });
            }
            if (Schema::hasColumn('content_sync_admin_prefs', 'dedup_mode')) {
                Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                    $table->dropColumn('dedup_mode');
                });
            }
        }
    }
};
