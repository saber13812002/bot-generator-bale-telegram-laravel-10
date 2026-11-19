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
            // فیلدهای ایمیل
            $table->string('email')->nullable()->after('settings')->comment('ایمیل کاربر');
            $table->timestamp('email_verified_at')->nullable()->after('email')->comment('زمان تایید ایمیل');
            $table->string('email_verification_code', 6)->nullable()->after('email_verified_at')->comment('کد تایید 6 رقمی');
            $table->timestamp('email_verification_code_expires_at')->nullable()->after('email_verification_code')->comment('زمان انقضای کد تایید');
            
            // تنظیمات گزارش ایمیل
            $table->enum('email_report_frequency', ['daily', 'weekly', 'monthly', 'never'])
                  ->default('weekly')
                  ->after('email_verification_code_expires_at')
                  ->comment('فرکانس ارسال گزارش');
            $table->timestamp('last_email_report_sent_at')->nullable()->after('email_report_frequency')->comment('آخرین زمان ارسال گزارش');
            
            // توکن لغو اشتراک
            $table->string('email_unsubscribe_token', 64)->nullable()->unique()->after('last_email_report_sent_at')->comment('توکن یکتا برای لغو اشتراک');
            
            // Indexes
            $table->index('email');
            $table->index('email_unsubscribe_token');
            $table->index(['email_verified_at', 'email_report_frequency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropIndex(['bot_users_email_index']);
            $table->dropIndex(['bot_users_email_unsubscribe_token_unique']);
            $table->dropIndex(['bot_users_email_verified_at_email_report_frequency_index']);
            
            $table->dropColumn([
                'email',
                'email_verified_at',
                'email_verification_code',
                'email_verification_code_expires_at',
                'email_report_frequency',
                'last_email_report_sent_at',
                'email_unsubscribe_token'
            ]);
        });
    }
};
