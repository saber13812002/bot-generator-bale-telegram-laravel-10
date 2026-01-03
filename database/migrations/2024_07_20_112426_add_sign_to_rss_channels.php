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
        Schema::table('rss_channels', function (Blueprint $table) {
            $table->text('sign')->after('type')->nullable();
            $table->boolean('has_command')->default(0)->after('type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rss_channels', function (Blueprint $table) {
            $table->dropColumn('sign');
            $table->dropColumn('has_command');
        });
    }
};
