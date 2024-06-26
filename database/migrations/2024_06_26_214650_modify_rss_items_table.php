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
        Schema::table('rss_items', function (Blueprint $table) {
            $table->bigInteger('rss_business_id')->default(1)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->bigInteger('rss_business_id')->nullable(false)->change();
        });
    }
};
