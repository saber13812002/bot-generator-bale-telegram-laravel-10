<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyBotLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bot_logs', function (Blueprint $table) {
            // Modify the character set and collation of the 'text' column
            DB::statement('ALTER TABLE bot_logs CHANGE text text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bot_logs', function (Blueprint $table) {
            // Reverse the character set and collation of the 'text' column
            DB::statement('ALTER TABLE bot_logs CHANGE text text TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci;');
        });
    }
}

;
