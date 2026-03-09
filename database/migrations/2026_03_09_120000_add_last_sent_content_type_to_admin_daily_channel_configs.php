<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * برای گزینه ترتیبی (۶): آخرین نوع ارسال‌شده ذخیره می‌شود تا نوبت بعدی مشخص شود.
     */
    public function up(): void
    {
        Schema::table('admin_daily_channel_configs', function (Blueprint $table) {
            $table->string('last_sent_content_type', 30)->nullable()->after('content_type')
                ->comment('آخرین نوع ارسال‌شده برای content_type=sequential: verse|hadith|nahj|sharabe_beheshti');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_daily_channel_configs', function (Blueprint $table) {
            $table->dropColumn('last_sent_content_type');
        });
    }
};
