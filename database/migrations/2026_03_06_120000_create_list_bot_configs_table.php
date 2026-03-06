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
        Schema::create('list_bot_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->unique();
            $table->json('menu_json')->nullable()->comment('Tree: title, link?, children?');
            $table->text('raw_content')->nullable()->comment('Original menu text from admin');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_bot_configs');
    }
};
