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
            $table->unsignedBigInteger('bot_owner_id');
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('added_by_owner_id')->nullable();
            $table->timestamps();

            $table->unique(['bot_owner_id', 'bot_id']);

            // Foreign keys commented out for MyISAM compatibility.
            // Integrity is enforced at the application layer.
            // $table->foreign('bot_owner_id')->references('id')->on('bot_owners')->onDelete('cascade');
            // $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            // $table->foreign('added_by_owner_id')->references('id')->on('bot_owners')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_admin_panel_users');
    }
};
