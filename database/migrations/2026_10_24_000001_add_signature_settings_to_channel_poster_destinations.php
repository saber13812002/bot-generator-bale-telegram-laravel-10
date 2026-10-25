<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مدیریت پیشرفتهٔ امضا (الحاقیهٔ ۱ مأموریت امضا):
 * ذخیرهٔ داده‌های معنایی امضا به‌ازای هر مقصد — نه فرمت نهایی HTML/Markdown.
 *
 * - signature_label   : متن نمایشی که کاربر در ویزارد /sign ارسال می‌کند (null = برچسب پیش‌فرض پلتفرم)
 * - signature_type    : نوع امضا (inline | raw | plain | button | composite | none) — پیش‌فرض inline
 * - signature_enabled : null = رفتار افسانه‌ای (لینک ذخیره‌شده یعنی فعال) — سازگاری معکوس؛
 *                       ویزارد جدید مقدار صریح true/false می‌گذارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_poster_destinations', function (Blueprint $table) {
            $table->string('signature_label', 255)->nullable()->after('channel_link');
            $table->string('signature_type', 20)->default('inline')->after('signature_label');
            $table->boolean('signature_enabled')->nullable()->default(null)->after('signature_type');
        });
    }

    public function down(): void
    {
        Schema::table('channel_poster_destinations', function (Blueprint $table) {
            $table->dropColumn(['signature_label', 'signature_type', 'signature_enabled']);
        });
    }
};
