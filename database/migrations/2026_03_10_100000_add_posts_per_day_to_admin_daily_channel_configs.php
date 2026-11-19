<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * تعداد ارسال در روز: ۱ = یک بار، ۲ = دو بار، ۴ = چهار بار.
     * برای MSSQL دستی: ALTER TABLE admin_daily_channel_configs ADD posts_per_day TINYINT NOT NULL DEFAULT 1;
     */
    public function up(): void
    {
        Schema::table('admin_daily_channel_configs', function (Blueprint $table) {
            $table->unsignedTinyInteger('posts_per_day')->default(1)->after('is_active')
                ->comment('1=یک بار در روز، 2=دو بار، 4=چهار بار');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_daily_channel_configs', function (Blueprint $table) {
            $table->dropColumn('posts_per_day');
        });
    }
};
