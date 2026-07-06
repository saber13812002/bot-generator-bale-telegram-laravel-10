<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->text('caption_footer')->nullable()->after('supported_message_template')
                ->comment('متن ثابت انتهای کپشن برای همه ارسال‌های محتوا (مثلاً لینک کانال یا ربات)');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn('caption_footer');
        });
    }
};
