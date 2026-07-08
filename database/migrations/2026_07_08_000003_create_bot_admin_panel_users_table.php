<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_admin_panel_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_owner_id')->constrained('bot_owners')->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained('bots')->cascadeOnDelete();
            $table->foreignId('added_by_owner_id')->nullable()->constrained('bot_owners')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bot_owner_id', 'bot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_admin_panel_users');
    }
};
