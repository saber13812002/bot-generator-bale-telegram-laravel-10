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
        Schema::table('rss_feed_web_origins', function (Blueprint $table) {
            $table->string('link')->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rss_feed_web_origins', function (Blueprint $table) {
            $table->dropUnique(['link']);
            $table->string('link')->change(); // Revert to non-unique if needed
        });
    }
};
