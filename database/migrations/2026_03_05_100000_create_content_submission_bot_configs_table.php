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
        Schema::create('content_submission_bot_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->comment('شناسه ربات');
            $table->bigInteger('channel_chat_id')->comment('شناسه کانال انتشار');
            $table->bigInteger('group_chat_id')->nullable()->comment('شناسه گروه تایید (در صورت نیاز به تایید)');
            $table->unsignedTinyInteger('required_approvals')->default(1)->comment('۱ یا ۲؛ ۰ یعنی بدون گروه تایید');
            $table->enum('origin', ['telegram', 'bale'])->comment('پلتفرم');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->unique('bot_id');
            $table->index(['bot_id', 'origin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_submission_bot_configs');
    }
};
