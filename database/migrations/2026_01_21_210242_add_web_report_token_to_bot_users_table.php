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
        Schema::table('bot_users', function (Blueprint $table) {
            // توکن دسترسی به صفحه گزارش وب
            $table->string('web_report_token', 64)->nullable()->unique()->after('email_unsubscribe_token')->comment('توکن یکتا برای دسترسی به صفحه گزارش وب');
            
            // Index
            $table->index('web_report_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropIndex(['bot_users_web_report_token_index']);
            $table->dropColumn('web_report_token');
        });
    }
};
