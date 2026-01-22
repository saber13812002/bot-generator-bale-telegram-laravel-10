<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            // لینک‌های نمونه ربات
            $table->string('sample_telegram_link')->nullable()->after('is_active')->comment('لینک نمونه ربات در Telegram');
            $table->string('sample_bale_link')->nullable()->after('sample_telegram_link')->comment('لینک نمونه ربات در Bale');
            
            // لینک‌های وبلاگ
            $table->string('blog_virgool_link')->nullable()->after('sample_bale_link')->comment('لینک وبلاگ فارسی در ویرگول');
            $table->string('blog_medium_link')->nullable()->after('blog_virgool_link')->comment('لینک وبلاگ انگلیسی در Medium');
            
            // آیکون‌ها
            $table->string('icon_emoji')->nullable()->after('blog_medium_link')->comment('آیکون Emoji برای نمایش در صفحه اصلی');
            $table->text('icon_svg')->nullable()->after('icon_emoji')->comment('آیکون SVG سفارشی برای نمایش در صفحه اصلی');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'sample_telegram_link',
                'sample_bale_link',
                'blog_virgool_link',
                'blog_medium_link',
                'icon_emoji',
                'icon_svg',
            ]);
        });
    }
};
