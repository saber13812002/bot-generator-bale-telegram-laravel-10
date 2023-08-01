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
        Schema::table('bot_mothers', function (Blueprint $table) {
            $table->enum('type', ['bale', 'telegram', 'gap', 'soroosh'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_mothers', function (Blueprint $table) {
            $table->enum('type', ['bale', 'telegram'])->nullable()->change();
        });
    }
};
