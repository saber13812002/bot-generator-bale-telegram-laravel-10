<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pending_uploads', function (Blueprint $table) {
            $table->text('file_id')->change();
        });
    }

    public function down(): void
    {
        // بازگشت به حالت قبل عملی نیست چون داده ممکن است بزرگتر باشد
    }
};
