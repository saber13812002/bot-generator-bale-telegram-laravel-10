<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * دو ستون جدید برای مدیریت سلامت فیدهای RSS:
     * - consecutive_failures: تعداد خطاهای متوالی در دریافت فید
     *   (با رسیدن به RssItemService::MAX_CONSECUTIVE_FAILURES، فید خودکار غیرفعال می‌شود)
     * - last_error: شرح آخرین خطا برای گزارش‌گیری و عیب‌یابی
     */
    public function up(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->string('last_error', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->dropColumn(['consecutive_failures', 'last_error']);
        });
    }
};
