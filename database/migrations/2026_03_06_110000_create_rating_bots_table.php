<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_bots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->unique();
            $table->text('content');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_bots');
    }
};

