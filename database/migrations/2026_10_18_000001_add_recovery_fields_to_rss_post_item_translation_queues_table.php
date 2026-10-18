<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستون‌های احیای صف انتشار RSS (P3).
 *
 * enum موجود ('queue','sent','error') دست‌نخورده می‌ماند (بدون doctrine/dbal
 * تغییر enum روی MySQL امن نیست). به‌جای آن ستون‌های additive و nullable
 * اضافه می‌شوند تا «سرنجه ارسال» ثبت شود:
 *
 * - message_id: شناسه پیام از API (تأیید واقعیِ تحویل پیام اصلی)
 * - sent_at:     زمان ثبت موفقیت (تفکیک رکوردهای قدیمی از جدید)
 * - attempts:    تعداد تلاش‌های ارسال (برای بازتست‌های تکراری)
 * - last_error:  دلیل آخرین خطا (redact شده)
 * - media_error: خطای رسانه بعد از موفقیتِ پیام اصلی (تمایز از شکستِ کامل)
 *
 * با این سرنجه‌ها، ریکاوری می‌تواند رکوردهای «واقعا‌ ارسال‌نشده» را از
 * رکوردهای «شک دار/نامشخص» تفکیک کند (بدون حدس زدن).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_post_item_translation_queues', function (Blueprint $table) {
            $table->string('message_id', 255)->nullable()->after('status');
            $table->timestamp('sent_at')->nullable()->after('message_id');
            $table->unsignedTinyInteger('attempts')->default(0)->after('sent_at');
            $table->string('last_error', 500)->nullable()->after('attempts');
            $table->string('media_error', 500)->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('rss_post_item_translation_queues', function (Blueprint $table) {
            $table->dropColumn(['message_id', 'sent_at', 'attempts', 'last_error', 'media_error']);
        });
    }
};
