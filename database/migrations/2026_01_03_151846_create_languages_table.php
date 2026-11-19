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
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            
            // کد زبان (مثلاً fa, en, ar-IQ)
            $table->string('code', 10)->unique()->index();
            
            // نام زبان (مثلاً فارسی, English)
            $table->string('name');
            
            // نام بومی زبان (مثلاً فارسی, English)
            $table->string('native_name')->nullable();
            
            // ایموجی پرچم (مثلاً 🇮🇷, 🇬🇧)
            $table->string('flag_emoji')->nullable();
            
            // نمایش کامل با ایموجی (مثلاً 🇮🇷 فارسی)
            $table->string('display_name')->nullable();
            
            // وضعیت فعال/غیرفعال
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
