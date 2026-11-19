<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول استاندارد jobs برای queue driver 'database' (WS-D).
 *
 * تا پیش از این فقط failed_jobs وجود داشت و jobs نمی‌بود؛ بنابراین
 * queue driver 'database' بدون این جدول کار می‌کرد. AI jobs صریحاً
 * onConnection('database')->onQueue('ai') می‌روند؛ QUEUE_CONNECTION
 * پیش‌فرض (sync) بقیه اپ دست‌نخورده می‌ماند.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index(['queue', 'reserved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
