<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['task_id']);
        });

        Schema::table('prompts', function (Blueprint $table) {
            // Make task_id nullable
            $table->unsignedBigInteger('task_id')->nullable()->change();
        });

        Schema::table('prompts', function (Blueprint $table) {
            // Re-add foreign key constraint with nullable support
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['task_id']);
        });

        Schema::table('prompts', function (Blueprint $table) {
            // Make task_id not nullable again
            $table->unsignedBigInteger('task_id')->nullable(false)->change();
        });

        Schema::table('prompts', function (Blueprint $table) {
            // Re-add foreign key constraint
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }
};
