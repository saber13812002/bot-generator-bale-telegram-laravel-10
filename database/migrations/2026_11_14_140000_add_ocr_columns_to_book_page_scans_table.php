<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS-F — OCR MVP در Book Pixel.
 *
 * ستون‌های نتیجه OCR روی book_page_scans:
 *  - ocr_text  : متن استخراج‌شده (فقط وقتی موفق بوده؛ در خطا null می‌ماند)
 *  - ocr_error : error_code آخرین تلاش ناموفق (provider_error, empty_result, ...)
 *  - ocr_at    : زمان آخرین تلاش (موفق یا ناموفق)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('book_page_scans', 'ocr_text')) {
            Schema::table('book_page_scans', function (Blueprint $table) {
                $table->longText('ocr_text')->nullable()->after('rejection_reason');
                $table->string('ocr_error', 120)->nullable()->after('ocr_text');
                $table->timestamp('ocr_at')->nullable()->after('ocr_error');
            });
        }
    }

    public function down(): void
    {
        Schema::table('book_page_scans', function (Blueprint $table) {
            $table->dropColumn(['ocr_text', 'ocr_error', 'ocr_at']);
        });
    }
};
