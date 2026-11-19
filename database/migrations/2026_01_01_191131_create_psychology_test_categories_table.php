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
        Schema::create('psychology_test_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('psychology_test_bot_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('psychology_test_bot_id')->references('id')->on('psychology_test_bots')->onDelete('cascade');
            $table->unique(['psychology_test_bot_id', 'name'], 'psych_test_categories_bot_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psychology_test_categories');
    }
};
