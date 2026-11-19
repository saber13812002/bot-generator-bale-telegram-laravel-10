<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            if (!Schema::hasColumn('bots', 'last_activity_at')) {
                $table->timestamp('last_activity_at')
                    ->nullable()
                    ->after('supported_message_template')
                    ->comment('آخرین فعالیت موفق ربات');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            if (Schema::hasColumn('bots', 'last_activity_at')) {
                $table->dropColumn('last_activity_at');
            }
        });
    }
};
