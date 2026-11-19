<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('channel_poster_destinations')) {
            return;
        }

        Schema::table('channel_poster_destinations', function (Blueprint $table) {
            if (!Schema::hasColumn('channel_poster_destinations', 'channel_link')) {
                $table->string('channel_link', 255)->nullable()->after('channel_title');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('channel_poster_destinations')) {
            return;
        }

        Schema::table('channel_poster_destinations', function (Blueprint $table) {
            if (Schema::hasColumn('channel_poster_destinations', 'channel_link')) {
                $table->dropColumn('channel_link');
            }
        });
    }
};
