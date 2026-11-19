<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Soroush Plus & Rubika bots (ADDITIVE ONLY, non-destructive).
 *
 * 1) Adds per-platform columns on `bots` mirroring the existing
 *    telegram_* and bale_* column conventions so soroush/rubika mother and
 *    child bots can be registered like telegram/bale bots.
 *
 * 2) (MySQL only) Extends the `bots.type` enum with 'soroush'/'rubika' so
 *    the registration flow can store the platform of the new bots. This is
 *    additive: no existing enum value is removed or reordered.
 *
 * 3) (MySQL only) Additively extends `bot_logs.type` with 'soroush'/'rubika'
 *    because the new native webhook flow calls LogHelper::log() with these
 *    types. This is a pure value addition; the legacy `soroosh` rename and
 *    the normalization of the other tables (bot_mothers.type,
 *    bot_users.origin, blog_users.type, quran_scan_pages.type) remain Phase 6.
 */
return new class extends Migration {
    private const NEW_COLUMNS = [
        'soroush_owner_chat_id',
        'soroush_bot_name',
        'soroush_bot_token',
        'soroush_get_me_api_response',
        'soroush_bot_status',
        'soroush_webhook_is_set',
        'rubika_owner_chat_id',
        'rubika_bot_name',
        'rubika_bot_token',
        'rubika_get_me_api_response',
        'rubika_bot_status',
        'rubika_webhook_is_set',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('bots')) {
            return;
        }

        Schema::table('bots', function (Blueprint $table) {
            if (!Schema::hasColumn('bots', 'soroush_owner_chat_id')) {
                $table->bigInteger('soroush_owner_chat_id')->nullable();
            }
            if (!Schema::hasColumn('bots', 'soroush_bot_name')) {
                $table->string('soroush_bot_name')->nullable()->unique();
            }
            if (!Schema::hasColumn('bots', 'soroush_bot_token')) {
                $table->string('soroush_bot_token')->nullable();
            }
            if (!Schema::hasColumn('bots', 'soroush_get_me_api_response')) {
                $table->json('soroush_get_me_api_response')->nullable();
            }
            if (!Schema::hasColumn('bots', 'soroush_bot_status')) {
                $table->enum('soroush_bot_status', ['Active', 'DeActive'])->default('DeActive');
            }
            if (!Schema::hasColumn('bots', 'soroush_webhook_is_set')) {
                $table->boolean('soroush_webhook_is_set')->default(false);
            }

            if (!Schema::hasColumn('bots', 'rubika_owner_chat_id')) {
                $table->bigInteger('rubika_owner_chat_id')->nullable();
            }
            if (!Schema::hasColumn('bots', 'rubika_bot_name')) {
                $table->string('rubika_bot_name')->nullable()->unique();
            }
            if (!Schema::hasColumn('bots', 'rubika_bot_token')) {
                $table->string('rubika_bot_token')->nullable();
            }
            if (!Schema::hasColumn('bots', 'rubika_get_me_api_response')) {
                $table->json('rubika_get_me_api_response')->nullable();
            }
            if (!Schema::hasColumn('bots', 'rubika_bot_status')) {
                $table->enum('rubika_bot_status', ['Active', 'DeActive'])->default('DeActive');
            }
            if (!Schema::hasColumn('bots', 'rubika_webhook_is_set')) {
                $table->boolean('rubika_webhook_is_set')->default(false);
            }
        });

        // Extend bots.type so registration can store 'soroush'/'rubika'.
        // MySQL-only (raw SQL), mirroring the repo's existing enum MODIFY
        // pattern; skipped on SQLite and when the column is absent.
        if (DB::connection()->getDriverName() === 'mysql') {
            if (Schema::hasColumn('bots', 'type')) {
                DB::statement(
                    "ALTER TABLE `bots` MODIFY COLUMN `type` ENUM('telegram', 'bale', 'soroush', 'rubika') NULL"
                );
            }

            // LogHelper::log() writes the platform as bot_logs.type in the
            // new native webhook flow, so the enum must accept the values.
            if (Schema::hasTable('bot_logs') && Schema::hasColumn('bot_logs', 'type')) {
                DB::statement(
                    "ALTER TABLE `bot_logs` MODIFY COLUMN `type` ENUM('bale', 'telegram', 'gap', 'soroosh', 'soroush', 'rubika') NULL"
                );
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('bots')) {
            return;
        }

        Schema::table('bots', function (Blueprint $table) {
            foreach (self::NEW_COLUMNS as $column) {
                if (Schema::hasColumn('bots', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
