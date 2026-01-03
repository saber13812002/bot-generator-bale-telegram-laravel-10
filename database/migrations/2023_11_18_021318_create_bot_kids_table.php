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
        Schema::create('bot_kids', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('bot_mother_id')->nullable();
            $table->string('token');
            $table->unsignedBigInteger('first_chat_id');
            $table->enum('type', ['bale', 'telegram']);
            $table->string('locale', 7)->default('fa');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_kids');
    }
};
