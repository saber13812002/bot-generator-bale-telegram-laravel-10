<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pro_purchase_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('admin_notes');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->index('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pro_purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['approved_at']);
            $table->dropColumn(['approved_by', 'approved_at']);
        });
    }
};
