<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS-G — ASR MVP در Book Pixel.
 *
 * ستون‌های نتیجه ASR روی book_page_voices:
 *  - asr_text  : متن تبدیل‌شده از وویس (فقط وقتی موفق بوده؛ در خطا null می‌ماند)
 *  - asr_error : error_code آخرین تلاش ناموفق (provider_error, endpoint_missing, ...)
 *  - asr_at    : زمان آخرین تلاش (موفق یا ناموفق)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('book_page_voices', 'asr_text')) {
            Schema::table('book_page_voices', function (Blueprint $table) {
                $table->longText('asr_text')->nullable()->after('points_awarded');
                $table->string('asr_error', 120)->nullable()->after('asr_text');
                $table->timestamp('asr_at')->nullable()->after('asr_error');
            });
        }
    }

    public function down(): void
    {
        Schema::table('book_page_voices', function (Blueprint $table) {
            $table->dropColumn(['asr_text', 'asr_error', 'asr_at']);
        });
    }
};
