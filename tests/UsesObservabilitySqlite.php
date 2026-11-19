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
        Schema::dropIfExists('bot_logs');
        Schema::dropIfExists('bot_users');
        Schema::dropIfExists('webhook_endpoints');
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
            $table->string('endpoint_id')->nullable();
            $table->string('bale_bot_name')->nullable();
            $table->string('bale_bot_token')->nullable();
            $table->string('bale_bot_status')->nullable();
            $table->boolean('bale_webhook_is_set')->default(false);
            $table->string('telegram_bot_name')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_bot_status')->nullable();
            $table->boolean('telegram_webhook_is_set')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_id');
            $table->string('name');
            $table->string('route')->nullable();
            $table->timestamps();
        });

        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('status')->default('active');
            $table->string('origin');
            $table->timestamps();
        });

        Schema::create('bot_logs', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_endpoint_uri')->nullable();
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->string('type')->nullable();
            $table->string('text')->nullable();
            $table->bigInteger('chat_id')->nullable();
            $table->timestamps();
        });
    }
}
