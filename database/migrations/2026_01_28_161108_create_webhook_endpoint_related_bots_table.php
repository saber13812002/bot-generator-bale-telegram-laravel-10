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
        // حذف جدول در صورت وجود (برای حل مشکل migration قبلی)
        Schema::dropIfExists('webhook_endpoint_related_bots');
        
        Schema::create('webhook_endpoint_related_bots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_endpoint_id');
            $table->unsignedBigInteger('related_webhook_endpoint_id');
            $table->integer('order')->default(0)->comment('ترتیب نمایش ربات‌های مرتبط');
            $table->timestamps();
            
            // Foreign keys with shorter constraint names
            $table->foreign('webhook_endpoint_id', 'fk_webhook_endpoint_id')
                  ->references('id')
                  ->on('webhook_endpoints')
                  ->onDelete('cascade');
            
            $table->foreign('related_webhook_endpoint_id', 'fk_related_webhook_endpoint_id')
                  ->references('id')
                  ->on('webhook_endpoints')
                  ->onDelete('cascade');
            
            // جلوگیری از تکرار رابطه
            $table->unique(['webhook_endpoint_id', 'related_webhook_endpoint_id'], 'unique_related_bots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoint_related_bots');
    }
};
