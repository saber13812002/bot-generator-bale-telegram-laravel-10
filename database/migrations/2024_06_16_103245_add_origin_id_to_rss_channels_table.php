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
        Schema::table('rss_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('origin_id')->after('type')->nullable()->after('id');

            $table->foreign('origin_id')
                ->references('id')
                ->on('rss_channel_origins')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rss_channels', function (Blueprint $table) {
            $table->dropForeign(['origin_id']);
            $table->dropColumn('origin_id');
        });
    }
};
