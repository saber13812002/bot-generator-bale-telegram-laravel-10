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
        // Get foreign key constraint name
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'prompts' 
            AND COLUMN_NAME = 'task_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop foreign key constraint if exists
        if (!empty($foreignKeys)) {
            $constraintName = $foreignKeys[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `prompts` DROP FOREIGN KEY `{$constraintName}`");
        }

        // Make task_id nullable
        Schema::table('prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable()->change();
        });

        // Re-add foreign key constraint with nullable support
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get foreign key constraint name
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'prompts' 
            AND COLUMN_NAME = 'task_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop foreign key constraint if exists
        if (!empty($foreignKeys)) {
            $constraintName = $foreignKeys[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `prompts` DROP FOREIGN KEY `{$constraintName}`");
        }

        // Make task_id not nullable again
        Schema::table('prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable(false)->change();
        });

        // Re-add foreign key constraint
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });
    }
};
