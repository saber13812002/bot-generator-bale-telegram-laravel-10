<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('content_notes')) {
            Schema::create('content_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('content_item_id')->constrained('content_items')->onDelete('cascade');
                $table->foreignId('bot_user_id')->constrained('bot_users')->onDelete('cascade');
                $table->enum('type', ['note', 'question']);
                $table->text('text');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_notes');
    }
};
