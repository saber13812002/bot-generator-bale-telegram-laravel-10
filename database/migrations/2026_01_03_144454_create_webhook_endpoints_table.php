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
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            
            // شناسه endpoint (مثلاً webhook-personnel-registration)
            $table->string('endpoint_id')->unique()->index();
            
            // نام endpoint
            $table->string('name');
            
            // Route endpoint
            $table->string('route');
            
            // توضیحات
            $table->text('description')->nullable();
            
            // نیاز به bot_mother_id
            $table->boolean('requires_bot_mother_id')->default(false);
            
            // نیاز به token
            $table->boolean('requires_token')->default(false);
            
            // نیاز به language
            $table->boolean('requires_language')->default(false);
            
            // پشتیبانی از چند زبان
            $table->boolean('supports_multiple_languages')->default(false);
            
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
        Schema::dropIfExists('webhook_endpoints');
    }
};
