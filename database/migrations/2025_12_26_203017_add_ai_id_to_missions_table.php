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
        Schema::table('missions', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_id')->nullable()->after('content_id');
            $table->foreign('ai_id')->references('id')->on('ai_llms')->onDelete('set null');
            $table->index('ai_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropForeign(['ai_id']);
            $table->dropIndex(['ai_id']);
            $table->dropColumn('ai_id');
        });
    }
};
