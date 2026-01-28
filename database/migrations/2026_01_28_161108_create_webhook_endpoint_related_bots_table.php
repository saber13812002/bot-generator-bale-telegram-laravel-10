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
        Schema::create('webhook_endpoint_related_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->onDelete('cascade');
            $table->foreignId('related_webhook_endpoint_id')->constrained('webhook_endpoints')->onDelete('cascade');
            $table->integer('order')->default(0)->comment('ترتیب نمایش ربات‌های مرتبط');
            $table->timestamps();
            
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
