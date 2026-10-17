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
            $table->string('channel_username', 128)->nullable();
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
            $table->string('media_path', 1024)->nullable();
            $table->boolean('signature_enabled')->default(false);
            $table->dateTime('scheduled_at');
            $table->dateTime('published_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('owner_chat_id', 64)->nullable();
            $table->string('owner_origin', 20)->default('bale');
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('source_attribution', 20)->nullable();
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
            $table->string('post_url', 512)->nullable();
            $table->string('link_status', 20)->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->timestamps();
        });

        $this->setUpContentSyncTables();
    }

    /**
     * جدول‌های همگام‌سازی چندکاناله (Content Sync)
     */
    protected function setUpContentSyncTables(): void
    {
        Schema::dropIfExists('content_sync_admins');
        Schema::dropIfExists('content_sync_admin_prefs');
        Schema::dropIfExists('content_sync_bot_defaults');
        Schema::dropIfExists('content_sync_operations');
        Schema::dropIfExists('content_sync_versions');
        Schema::dropIfExists('content_sync_contents');

        Schema::create('content_sync_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->text('text')->nullable();
            $table->char('text_hash', 64)->nullable();
            $table->string('content_type', 20)->default('text');
            $table->string('created_by_chat_id', 64)->nullable();
            $table->string('created_by_origin', 20)->default('bale');
            $table->string('source_platform', 20);
            $table->string('source_channel_id', 128);
            $table->string('source_message_id', 64);
            $table->string('source_url', 512);
            $table->string('source_url_raw', 512)->nullable();
            $table->string('tag', 64)->nullable();
            $table->timestamps();
            $table->unique(
                ['bot_id', 'source_platform', 'source_channel_id', 'source_message_id'],
                'cs_contents_source_unique'
            );
        });

        Schema::create('content_sync_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->string('platform', 20);
            $table->string('channel_id', 128);
            $table->string('channel_username', 128)->nullable();
            $table->string('message_id', 128)->nullable();
            $table->string('post_url', 512)->nullable();
            $table->string('link_status', 20)->default('none');
            $table->string('publish_status', 20)->default('pending');
            $table->dateTime('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('queue_id')->nullable();
            $table->integer('chunks_count')->default(1);
            $table->timestamps();
            $table->unique(
                ['content_id', 'destination_id'],
                'cs_versions_content_destination_unique'
            );
        });

        Schema::create('content_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('admin_chat_id', 64)->nullable();
            $table->string('admin_origin', 20)->default('bale');
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('operation', 40);
            $table->string('result', 20)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();
        });

        Schema::create('content_sync_admin_prefs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('admin_chat_id', 64);
            $table->string('origin', 20)->default('bale');
            $table->string('last_tag', 64)->nullable();
            $table->string('publish_mode', 20)->default('immediate');
            $table->string('dedup_mode', 30)->default('exclude_source_channel');
            $table->boolean('fast_mode')->default(true);
            $table->boolean('settings_asked')->default(false);
            $table->string('source_attribution', 20)->default('signed');
            $table->timestamps();
            $table->unique(['bot_id', 'admin_chat_id', 'origin'], 'cs_prefs_admin_unique');
        });

        Schema::create('content_sync_bot_defaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('origin', 20)->default('bale');
            $table->string('set_by_chat_id', 64)->nullable();
            $table->string('publish_mode', 20)->default('immediate');
            $table->string('dedup_mode', 30)->default('exclude_source_channel');
            $table->boolean('fast_mode')->default(true);
            $table->string('source_attribution', 20)->default('signed');
            $table->timestamps();
            $table->unique(['bot_id', 'origin'], 'cs_bot_defaults_unique');
        });

        Schema::create('content_sync_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('admin_chat_id', 64);
            $table->string('origin', 20)->default('bale');
            $table->timestamps();
            $table->unique(['bot_id', 'admin_chat_id', 'origin'], 'cs_admins_unique');
        });
    }
}
