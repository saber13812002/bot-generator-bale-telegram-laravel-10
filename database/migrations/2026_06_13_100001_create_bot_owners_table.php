<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_owners', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 15)->unique();
            $table->string('name')->nullable();
            $table->string('bale_chat_id')->nullable()->index();
            $table->boolean('is_pro')->default(false);
            $table->timestamp('pro_confirmed_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_owners');
    }
};
