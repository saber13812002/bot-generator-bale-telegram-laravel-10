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
        Schema::create('email_threshold_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('threshold_type', ['daily', 'weekly', 'monthly'])->unique();
            $table->integer('max_emails')->default(100);
            $table->integer('current_count')->default(0);
            $table->datetime('reset_at')->nullable();
            $table->timestamps();
            
            $table->index('threshold_type');
            $table->index('reset_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_threshold_settings');
    }
};
