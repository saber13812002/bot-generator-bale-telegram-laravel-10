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
        Schema::create('content_submission_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->bigInteger('submitter_chat_id')->comment('شناسه چت فرستنده');
            $table->enum('content_type', ['text', 'image', 'video'])->comment('نوع محتوا');
            $table->text('content_text')->nullable()->comment('متن یا caption');
            $table->string('file_id')->nullable()->comment('file_id عکس/فیلم');
            $table->string('file_unique_id')->nullable()->comment('file_unique_id');
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'published'])->default('pending_approval');
            $table->bigInteger('approval_message_id')->nullable()->comment('شناسه پیام در گروه تایید');
            $table->bigInteger('first_approver_chat_id')->nullable()->comment('تاییدکننده اول');
            $table->bigInteger('second_approver_chat_id')->nullable()->comment('تاییدکننده دوم');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->bigInteger('channel_message_id')->nullable()->comment('شناسه پیام در کانال بعد از انتشار');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->index('bot_id');
            $table->index('status');
            $table->index('approval_message_id');
            $table->index(['bot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_submission_items');
    }
};
