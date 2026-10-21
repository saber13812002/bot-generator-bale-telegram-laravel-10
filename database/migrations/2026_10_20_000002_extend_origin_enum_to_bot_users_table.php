<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — engine generalization:
 *
 * `bot_users.origin` enum lacks 'soroush'/'rubika'. The channel-poster
 * webhook (Phase 4) creates BotUsers rows with origin=$type, so a
 * soroush/rubika mother bot receiving its first /start would fail on
 * MySQL. This additive migration extends the enum while keeping the
 * legacy 'soroosh' value (its normalization is Phase 6 scope).
 *
 * MySQL-only (raw SQL), mirroring the repo's existing enum MODIFY
 * pattern (2023_07_28_133734); skipped on SQLite and when the column
 * is absent. Down() restores the Phase-4 enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            if (!Schema::hasTable('bot_users')) {
                return;
            }

            $columnExists = DB::select("SHOW COLUMNS FROM `bot_users` LIKE 'origin'");
            if (empty($columnExists)) {
                return;
            }

            DB::statement(
                "ALTER TABLE `bot_users` MODIFY COLUMN `origin` ENUM('bale', 'telegram', 'gap', 'soroosh', 'soroush', 'rubika') NULL"
            );
        } catch (\Exception $e) {
            // Additive-only change: never fail the deployment over it.
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            if (!Schema::hasTable('bot_users')) {
                return;
            }

            $columnExists = DB::select("SHOW COLUMNS FROM `bot_users` LIKE 'origin'");
            if (empty($columnExists)) {
                return;
            }

            DB::statement(
                "ALTER TABLE `bot_users` MODIFY COLUMN `origin` ENUM('bale', 'telegram', 'gap', 'soroosh') NULL"
            );
        } catch (\Exception $e) {
            // Additive-only change: never fail the deployment over it.
        }
    }
};
