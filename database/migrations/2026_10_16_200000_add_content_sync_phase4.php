<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تغییرات فاز ۴ همگام‌سازی چندکاناله (Content Sync — phase 4)
 *
 * - channel_poster_destinations.channel_username: username عمومی قابل‌اعتماد کانال
 *   (برگرفته از لینک واقعی کانال هنگام اتصال؛ منبع مطمئن برای ساخت post_url).
 * - content_sync_admin_prefs.source_attribution: روش نمایش منبع (signed | link_only | none)
 *   — یک‌بار پرسیده می‌شود و دیگر تکرار نمی‌شود.
 * - content_sync_bot_defaults.source_attribution: پیش‌فرض مشترک کل ربات (تنظیم توسط مالک).
 * - channel_poster_queue.source_attribution: حالت سبک‌نویسی (freeze) در لحظه‌ی صف —
 *   آیتم‌های صفی‌شده با همان روشی منتشر می‌شوند که هنگام صف‌کردن فعال بوده.
 *
 * مهاجرت محافظت‌شده: اگر ستون قبلاً وجود داشته باشد، دست نمی‌خورد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_poster_destinations')) {
            if (!Schema::hasColumn('channel_poster_destinations', 'channel_username')) {
                Schema::table('channel_poster_destinations', function (Blueprint $table) {
                    // username عمومی قابل‌اعتماد (مثلا‌َ saberblog از ble.ir/saberblog/... یا t.me/saberblog)
                    $table->string('channel_username', 128)->nullable()->after('channel_link');
                });
            }
        }

        if (Schema::hasTable('content_sync_admin_prefs')) {
            if (!Schema::hasColumn('content_sync_admin_prefs', 'source_attribution')) {
                Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                    // signed | link_only | none
                    $table->string('source_attribution', 20)->default('signed')->after('settings_asked');
                });
            }
        }

        if (Schema::hasTable('content_sync_bot_defaults')) {
            if (!Schema::hasColumn('content_sync_bot_defaults', 'source_attribution')) {
                Schema::table('content_sync_bot_defaults', function (Blueprint $table) {
                    // signed | link_only | none
                    $table->string('source_attribution', 20)->default('signed')->after('fast_mode');
                });
            }
        }

        if (Schema::hasTable('channel_poster_queue')) {
            if (!Schema::hasColumn('channel_poster_queue', 'source_attribution')) {
                Schema::table('channel_poster_queue', function (Blueprint $table) {
                    // freeze: روش سبک‌نویسی در لحظه‌ی صف‌کردن (nullable برای آیتم‌های قدیمی)
                    $table->string('source_attribution', 20)->nullable()->after('content_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('channel_poster_destinations')
            && Schema::hasColumn('channel_poster_destinations', 'channel_username')) {
            Schema::table('channel_poster_destinations', function (Blueprint $table) {
                $table->dropColumn('channel_username');
            });
        }

        if (Schema::hasTable('content_sync_admin_prefs')
            && Schema::hasColumn('content_sync_admin_prefs', 'source_attribution')) {
            Schema::table('content_sync_admin_prefs', function (Blueprint $table) {
                $table->dropColumn('source_attribution');
            });
        }

        if (Schema::hasTable('content_sync_bot_defaults')
            && Schema::hasColumn('content_sync_bot_defaults', 'source_attribution')) {
            Schema::table('content_sync_bot_defaults', function (Blueprint $table) {
                $table->dropColumn('source_attribution');
            });
        }

        if (Schema::hasTable('channel_poster_queue')
            && Schema::hasColumn('channel_poster_queue', 'source_attribution')) {
            Schema::table('channel_poster_queue', function (Blueprint $table) {
                $table->dropColumn('source_attribution');
            });
        }
    }
};
