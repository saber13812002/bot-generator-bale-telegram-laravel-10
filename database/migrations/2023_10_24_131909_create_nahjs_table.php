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
        Schema::create('nahjs', function (Blueprint $table) {
            $table->id();
            $table->integer('category_id');
            $table->integer('number');
            $table->string('title');
            $table->text('persian');
            $table->text('arabic')->nullable();
            $table->text('english')->nullable();
            $table->text('dashti')->nullable();
            $table->string('arabic_link')->nullable();
            $table->string('english_link')->nullable();
            $table->string('dashti_link')->nullable();
            $table->string('persian_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nahjs');
    }
};
