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
        Schema::create('poem_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poem_id')->comment('شناسه شعر');
            $table->unsignedBigInteger('suggested_by')->comment('شناسه کاربر پیشنهاد دهنده');
            $table->text('line_content')->comment('محتوای مصرع پیشنهادی');
            $table->unsignedInteger('suggested_line_number')->nullable()->comment('شماره ترتیب پیشنهادی برای مصرع');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending')->comment('وضعیت: در انتظار، پذیرفته شده، رد شده');
            $table->timestamps();
            
            $table->foreign('poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->index('poem_id');
            $table->index('suggested_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poem_suggestions');
    }
};
