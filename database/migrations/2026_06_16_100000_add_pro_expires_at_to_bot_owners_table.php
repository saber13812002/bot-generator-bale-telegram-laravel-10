<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_owners', function (Blueprint $table) {
            $table->dateTime('pro_expires_at')->nullable()->after('pro_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bot_owners', function (Blueprint $table) {
            $table->dropColumn('pro_expires_at');
        });
    }
};
