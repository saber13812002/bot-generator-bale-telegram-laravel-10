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
            if (!Schema::hasColumn('channel_poster_destinations', 'tag')) {
                $table->string('tag', 64)->nullable()->after('channel_title');
            }
        });

        try {
            Schema::table('channel_poster_destinations', function (Blueprint $table) {
                $table->dropUnique('channel_poster_destinations_bot_platform_unique');
            });
        } catch (\Throwable $e) {
            // already dropped or never existed
        }

        try {
            Schema::table('channel_poster_destinations', function (Blueprint $table) {
                $table->unique(['bot_id', 'platform', 'channel_chat_id'], 'cp_dest_bot_plat_chat_unique');
            });
        } catch (\Throwable $e) {
            // already added
        }

        try {
            Schema::table('channel_poster_destinations', function (Blueprint $table) {
                $table->index(['bot_id', 'tag'], 'cp_dest_bot_tag_index');
            });
        } catch (\Throwable $e) {
            // already added
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('channel_poster_destinations')) {
            return;
        }

        try {
            Schema::table('channel_poster_destinations', function (Blueprint $table) {
                $table->dropUnique('cp_dest_bot_plat_chat_unique');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('channel_poster_destinations', function (Blueprint $table) {
                $table->dropIndex('cp_dest_bot_tag_index');
            });
        } catch (\Throwable $e) {
        }

        Schema::table('channel_poster_destinations', function (Blueprint $table) {
            if (Schema::hasColumn('channel_poster_destinations', 'tag')) {
                $table->dropColumn('tag');
            }
        });

        try {
            Schema::table('channel_poster_destinations', function (Blueprint $table) {
                $table->unique(['bot_id', 'platform'], 'channel_poster_destinations_bot_platform_unique');
            });
        } catch (\Throwable $e) {
        }
    }
};
