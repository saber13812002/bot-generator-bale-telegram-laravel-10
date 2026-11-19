<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bot_owners', 'pro_expires_at')) {
            return;
        }

        DB::statement('ALTER TABLE `bot_owners` MODIFY `pro_expires_at` DATETIME NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('bot_owners', 'pro_expires_at')) {
            return;
        }

        DB::statement('ALTER TABLE `bot_owners` MODIFY `pro_expires_at` TIMESTAMP NULL');
    }
};
