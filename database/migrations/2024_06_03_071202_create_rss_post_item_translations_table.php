<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rss_post_item_translations', function (Blueprint $table) {
            $table->id();

            $table->bigInteger("rss_post_item_id");
            $table->bigInteger("author_chat_id")->nullable();
            $table->string("locale", 8)->default('fa');

            $table->string("title");
            $table->text("content");

            $table->bigInteger('approved_by_chat_id')->nullable();
            $table->boolean('approved')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rss_post_item_translations');
    }
};
