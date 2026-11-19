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
        Schema::create('poem_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poem_id')->comment('شناسه شعر');
            $table->unsignedBigInteger('version_id')->nullable()->comment('شناسه نسخه (اختیاری)');
            $table->unsignedInteger('line_number')->comment('شماره ترتیب مصرع');
            $table->text('content')->comment('محتوای مصرع یا جمله');
            $table->enum('line_type', ['classic_line', 'novel_sentence'])->default('classic_line')->comment('نوع: مصرع کلاسیک یا جمله شعر نو');
            $table->timestamps();
            
            $table->foreign('poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->foreign('version_id')->references('id')->on('poem_versions')->onDelete('cascade');
            $table->index('poem_id');
            $table->index('version_id');
            $table->index(['poem_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poem_lines');
    }
};
