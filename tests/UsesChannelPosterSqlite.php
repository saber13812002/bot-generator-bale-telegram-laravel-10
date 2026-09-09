<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;

trait UsesChannelPosterSqlite
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

    protected function setUpChannelPosterTables(): void
    {
        Schema::dropIfExists('bot_user_states');
        Schema::dropIfExists('channel_poster_destinations');
        Schema::dropIfExists('bot_users');
        Schema::dropIfExists('bots');

        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_mother_id')->nullable();
            $table->string('endpoint_id')->nullable();
            $table->string('language_code')->nullable();
            $table->string('bale_bot_name')->nullable();
            $table->string('bale_bot_token')->nullable();
            $table->string('bale_bot_status')->nullable();
            $table->bigInteger('bale_owner_chat_id')->nullable();
            $table->boolean('bale_webhook_is_set')->default(false);
            $table->string('telegram_bot_name')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_bot_status')->nullable();
            $table->bigInteger('telegram_owner_chat_id')->nullable();
            $table->boolean('telegram_webhook_is_set')->default(false);
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

        Schema::create('bot_user_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->integer('bot_mother_id');
            $table->string('state', 50);
            $table->json('data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_poster_destinations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('platform', 20);
            $table->string('channel_chat_id', 64);
            $table->string('channel_title')->nullable();
            $table->string('channel_link', 255)->nullable();
            $table->string('tag', 64)->nullable();
            $table->string('bot_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['bot_id', 'platform', 'channel_chat_id'], 'cp_dest_bot_plat_chat_unique');
        });

        Schema::create('channel_poster_tag_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('tag', 64);
            $table->boolean('signature_enabled')->default(false);
            $table->timestamps();
            $table->unique(['bot_id', 'tag'], 'cp_tag_settings_bot_tag_unique');
        });

        Schema::create('channel_poster_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('tag', 64)->nullable();
            $table->string('content_type', 20);
            $table->text('text')->nullable();
            $table->string('file_id', 512)->nullable();
            $table->boolean('signature_enabled')->default(false);
            $table->dateTime('scheduled_at');
            $table->dateTime('published_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('owner_chat_id', 64)->nullable();
            $table->string('owner_origin', 20)->default('bale');
            $table->timestamps();
        });

        Schema::create('channel_poster_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('queue_id')->nullable();
            $table->unsignedBigInteger('destination_id');
            $table->string('platform', 20);
            $table->boolean('success');
            $table->string('message_id', 128)->nullable();
            $table->text('error')->nullable();
            $table->dateTime('published_at');
            $table->timestamps();
        });
    }
}
