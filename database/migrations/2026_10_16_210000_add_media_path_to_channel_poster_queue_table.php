<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مسیر فایل رسانه‌ای دانلود‌شده برای انتشار در مقصدهای دیگر.
 *
 * file_idِ بله/تلگرام فقط برای همان پلتفرم معتبر است؛ برای انتشار چندکاناله
 * فایل اصلی در لحظه‌ی صف‌بندی روی دیسک دانلود می‌شود تا هر پلتفرم بتواند
 * آن را به‌صورت multipart آپلود کند. پس از انتشار موفق فایل پاک می‌شود؛ در
 * صورت شکست برای امکان تکرار (watchdog) روی دیسک می‌ماند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_poster_queue', function (Blueprint $table) {
            $table->string('media_path', 1024)->nullable()->after('file_id');
        });
    }

    public function down(): void
    {
        Schema::table('channel_poster_queue', function (Blueprint $table) {
            $table->dropColumn('media_path');
        });
    }
};
