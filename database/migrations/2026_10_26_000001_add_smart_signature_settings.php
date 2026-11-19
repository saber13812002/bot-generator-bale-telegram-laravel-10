<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CP-SMART-SIGNATURE-SETTINGS — ذخیرهٔ تنظیمات هوشمند امضا.
 *
 * channel_poster_destinations.signature_settings (JSON, nullable):
 *   تنظیمات سه‌بخشیِ هوشمند امضا به‌ازای هر مقصد (کانال). null = رفتار
 *   افسانه‌ای بدون تغییر (سازگاری معکوس؛ ستون‌های semantic قدیمی
 *   signature_label/type/enabled همچنان حاکم‌اند).
 *
 *   ساختار JSON (همه اختیاری — هنگام خواندن با پیش‌فرض‌ها ترکیب می‌شوند):
 *   {
 *     "enabled": true,
 *     "link_selection_policy": "self_only|all_channels|exclude_self|custom|random",
 *     "layout": "single_line|multi_line",
 *     "separator": " | ",                    // فقط برای single_line
 *     "selected_destinations": [3, 7],       // فقط برای custom — همیشه ID مقصد (نه نام پلتفرم)
 *     "random_count": 2,                     // فقط برای random (1..N)
 *     "application_policy": "always_apply|ask_every_post|disabled"
 *   }
 *
 *   هیچ HTML/Markdown در این JSON ذخیره نمی‌شود — فقط داده‌های معنایی.
 *
 * channel_poster_queue.signature_selection (JSON, nullable):
 *   انتخاب فریزشدهٔ RANDOM برای هر مقصدِ هر آیتم صف — در لحظهٔ صف‌بانی
 *   (نه زمان انتشار/تلاش مجدد) محاسبه و ذخیره می‌شود تا retry/resend دقیقاً
 *   همان محتوای امضا را تولید کند. ساختار:
 *   { "12": [5, 9] }  // destination_id => [مقصدهای انتخاب‌شده]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_poster_destinations', function (Blueprint $table) {
            $table->json('signature_settings')->nullable()->after('signature_enabled');
        });

        Schema::table('channel_poster_queue', function (Blueprint $table) {
            $table->json('signature_selection')->nullable()->after('signature_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('channel_poster_queue', function (Blueprint $table) {
            $table->dropColumn('signature_selection');
        });

        Schema::table('channel_poster_destinations', function (Blueprint $table) {
            $table->dropColumn('signature_settings');
        });
    }
};
