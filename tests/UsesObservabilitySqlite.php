<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;

trait UsesObservabilitySqlite
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', false);
        $app->make('db')->purge();

        return $app;
    }

    protected function setUpObservabilityTables(): void
    {
        Schema::dropIfExists('bot_health_events');
        Schema::dropIfExists('app_log_entries');
        Schema::dropIfExists('bots');

        Schema::create('app_log_entries', function (Blueprint $table) {
            $table->id();
            $table->string('level', 32);
            $table->text('message');
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->string('feature_key', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('bot_health_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->string('feature_key', 64);
            $table->string('platform', 32);
            $table->string('event_type', 32)->default('channel_post');
            $table->string('status', 16);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->string('bale_bot_name')->nullable();
            $table->string('bale_bot_token')->nullable();
            $table->string('bale_bot_status')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }
}
