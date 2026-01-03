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
        Schema::create('rss_post_items', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('rss_item_id');

            $table->string('title',1000)->nullable();
            $table->string('link', 1000)->unique();
            $table->text('description')->nullable();
            $table->dateTime('pub_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rss_post_items');
    }
};
