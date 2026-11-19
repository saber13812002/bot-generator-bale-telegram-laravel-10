<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * صف‌های رسانه (لیست نام‌دار برای آیتم‌های متن/عکس/ویدیو).
     */
    public function up(): void
    {
        Schema::create('media_queues', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_chat_id')->comment('مالک صف در ربات مادر');
            $table->string('name', 100)->comment('نام صف');
            $table->timestamps();

            $table->index(['admin_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_queues');
    }
};
