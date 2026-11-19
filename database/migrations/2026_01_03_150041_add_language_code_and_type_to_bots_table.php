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
        Schema::table('bots', function (Blueprint $table) {
            $table->string('language_code', 10)->nullable()->after('endpoint_id')->index();
            $table->enum('type', ['telegram', 'bale'])->nullable()->after('language_code')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropIndex(['language_code']);
            $table->dropIndex(['type']);
            $table->dropColumn(['language_code', 'type']);
        });
    }
};
