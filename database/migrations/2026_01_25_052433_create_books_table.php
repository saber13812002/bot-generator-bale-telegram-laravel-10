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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('نام کتاب');
            $table->string('isbn')->nullable()->comment('ISBN');
            $table->string('shabak')->nullable()->comment('شابک');
            $table->string('cover_image_file_id')->nullable()->comment('file_id عکس جلد');
            $table->string('cover_image_file_unique_id')->nullable()->comment('file_unique_id عکس جلد');
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->comment('شناسه کاربر ایجادکننده');
            $table->timestamps();
            
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index('bot_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
