<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // تغییر ستون‌ها به TEXT برای پشتیبانی از file_idهای بلند بله
        DB::statement('ALTER TABLE content_pending_uploads MODIFY file_id TEXT');
        DB::statement('ALTER TABLE content_assets MODIFY bale_file_id TEXT');
        DB::statement('ALTER TABLE content_assets MODIFY telegram_file_id TEXT');
    }

    public function down(): void
    {
        // بازگشت به حالت قبل - فقط در صورتی که دیتای موجود اجازه دهد
    }
};
