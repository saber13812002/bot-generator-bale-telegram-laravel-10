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
        Schema::table('mission_personnel', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_ai_id')->nullable()->after('personnel_id');
            $table->foreign('selected_ai_id')->references('id')->on('ai_llms')->onDelete('set null');
            $table->index('selected_ai_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mission_personnel', function (Blueprint $table) {
            $table->dropForeign(['selected_ai_id']);
            $table->dropIndex(['selected_ai_id']);
            $table->dropColumn('selected_ai_id');
        });
    }
};
