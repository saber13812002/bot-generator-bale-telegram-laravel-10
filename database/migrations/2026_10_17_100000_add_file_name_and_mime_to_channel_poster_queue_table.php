<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نام اصلی فایل و MIME برای محتوای document/audio در صف channel-poster.
 *
 * با اضافه‌شدن پشتیبانی message.document (مثلاً MP3 که بله آن را به‌جای
 * message.audio می‌فرستد)، هویت فایل (نام و MIME) باید تا لحظه‌ی انتشار
 * (و تکرار/ری‌تروی) حفظ شود تا آپلود multipart در مقصدها نام و نوع
 * واقعی فایل را داشته باشد. هر دو ستون nullable هستند تا آیتم‌های
 * قدیمی و رسانه‌هایی بدون نام/مایم (عکس، ویدیو، وویس) بدون تغییر کار کنند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_poster_queue', function (Blueprint $table) {
            $table->string('file_name', 512)->nullable()->after('media_path');
            $table->string('mime_type', 128)->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('channel_poster_queue', function (Blueprint $table) {
            $table->dropColumn(['file_name', 'mime_type']);
        });
    }
};
