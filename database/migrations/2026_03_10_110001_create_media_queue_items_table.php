<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * آیتم‌های هر صف: متن، عکس، ویدیو با file_id per platform.
     */
    public function up(): void
    {
        Schema::create('media_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_queue_id')->constrained('media_queues')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0)->comment('ترتیب نمایش');
            $table->string('content_type', 20)->comment('text|photo|video');
            $table->text('content_text')->nullable()->comment('متن یا caption');
            $table->string('file_id_telegram', 255)->nullable();
            $table->string('file_id_bale', 255)->nullable();
            $table->timestamps();

            $table->index(['media_queue_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_queue_items');
    }
};
