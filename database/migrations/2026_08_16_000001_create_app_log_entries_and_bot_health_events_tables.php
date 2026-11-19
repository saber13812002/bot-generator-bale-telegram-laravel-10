<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('bot_health_events');
        Schema::dropIfExists('app_log_entries');
        Schema::enableForeignKeyConstraints();

        Schema::create('app_log_entries', function (Blueprint $table) {
            $table->id();
            $table->string('level', 32)->index();
            $table->text('message');
            $table->unsignedBigInteger('bot_id')->nullable()->index();
            $table->string('feature_key', 64)->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('bot_health_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->nullable()->index();
            $table->string('feature_key', 64)->index();
            $table->string('platform', 32)->index();
            $table->string('event_type', 32)->default('channel_post');
            $table->string('status', 16)->index();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_health_events');
        Schema::dropIfExists('app_log_entries');
    }
};
