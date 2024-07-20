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
        Schema::table('rss_items', function (Blueprint $table) {
            $table->dateTime('last_synced_at')->after('rss_channel_id')->useCurrent();
            $table->integer('interval_minutes')->after('rss_channel_id')->default(59);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
            $table->dropColumn('interval_minutes');
        });
    }
};
