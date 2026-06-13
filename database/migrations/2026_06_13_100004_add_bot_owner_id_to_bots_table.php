<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->foreignId('bot_owner_id')->nullable()->after('id')->constrained('bot_owners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bot_owner_id');
        });
    }
};
