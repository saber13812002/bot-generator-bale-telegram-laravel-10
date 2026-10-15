<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بانک اطلاعاتی همگام‌سازی چندکاناله (Content Sync)
 *
 * - content_sync_contents:   یک محتوای واحد با شناسه مشترک (content_id)
 * - content_sync_versions:   نسخه‌های منتشرشده آن محتوا در هر کانال/پیام‌رسان (با post_url و link_status)
 * - content_sync_operations: تاریخچه عملیات ادمین‌ها (audit)
 * - content_sync_admin_prefs: تنظیمات شخصی هر ادمین (آخرین تگ، حالت انتشار) — مخصوص همان ادمین و ربات
 * - content_sync_admins:      چند ادمین مجاز برای یک ربات
 *
 * سازگاری با اطلاعات قدیمی: جدول‌های channel_poster_* دست‌نخورده می‌مانند؛
 * فقط ستون‌های جدید (nullable) به queue و publish_logs اضافه می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('content_sync_contents')) {
            Schema::create('content_sync_contents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bot_id');
                $table->text('text')->nullable();
                $table->char('text_hash', 64)->nullable();
                $table->string('content_type', 20)->default('text');
                $table->string('created_by_chat_id', 64)->nullable();
                $table->string('created_by_origin', 20)->default('bale');
                // منبع اصلی (لینکی که ادمین فرستاده)
                $table->string('source_platform', 20);
                $table->string('source_channel_id', 128);
                $table->string('source_message_id', 64);
                $table->string('source_url', 512);
                $table->string('source_url_raw', 512)->nullable();
                $table->string('tag', 64)->nullable();
                $table->timestamps();

                // جلوگیری از ثبت تکراری یک پیام مبدأ توسط ادمین‌های مختلف (idempotent)
                $table->unique(
                    ['bot_id', 'source_platform', 'source_channel_id', 'source_message_id'],
                    'cs_contents_source_unique'
                );
                $table->index(['bot_id', 'source_url'], 'cs_contents_source_url_index');
                $table->index('bot_id');
            });
        }

        if (!Schema::hasTable('content_sync_versions')) {
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
                // confirmed | constructed | unverified | none
                $table->string('link_status', 20)->default('none');
                // pending | queued | published | failed | skipped
                $table->string('publish_status', 20)->default('pending');
                $table->dateTime('published_at')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('queue_id')->nullable();
                $table->integer('chunks_count')->default(1);
                $table->timestamps();

                // هر محتوا در هر مقصد فقط یک نسخه معتبر دارد (جلوگیری از انتشار مجدد)
                $table->unique(
                    ['content_id', 'destination_id'],
                    'cs_versions_content_destination_unique'
                );
                $table->index('content_id');
                $table->index(['platform', 'channel_id', 'message_id'], 'cs_versions_plat_chan_msg_index');
            });
        }

        if (!Schema::hasTable('content_sync_operations')) {
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

                $table->index(['bot_id', 'content_id', 'attempted_at'], 'cs_ops_content_index');
            });
        }

        if (!Schema::hasTable('content_sync_admin_prefs')) {
            Schema::create('content_sync_admin_prefs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bot_id');
                $table->string('admin_chat_id', 64);
                $table->string('origin', 20)->default('bale');
                $table->string('last_tag', 64)->nullable();
                // immediate | queue
                $table->string('publish_mode', 20)->default('immediate');
                $table->timestamps();

                $table->unique(['bot_id', 'admin_chat_id', 'origin'], 'cs_prefs_admin_unique');
            });
        }

        if (!Schema::hasTable('content_sync_admins')) {
            Schema::create('content_sync_admins', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bot_id');
                $table->string('admin_chat_id', 64);
                $table->string('origin', 20)->default('bale');
                $table->timestamps();

                $table->unique(['bot_id', 'admin_chat_id', 'origin'], 'cs_admins_unique');
            });
        }

        // توسعه سازگار جدول‌های قدیمی (ستون‌های nullable — اطلاعات قدیمی دست‌نخورده)
        if (Schema::hasTable('channel_poster_queue')
            && !Schema::hasColumn('channel_poster_queue', 'content_id')) {
            Schema::table('channel_poster_queue', function (Blueprint $table) {
                $table->unsignedBigInteger('content_id')->nullable();
                $table->index('content_id', 'cp_queue_content_index');
            });
        }

        if (Schema::hasTable('channel_poster_publish_logs')
            && !Schema::hasColumn('channel_poster_publish_logs', 'post_url')) {
            Schema::table('channel_poster_publish_logs', function (Blueprint $table) {
                $table->string('post_url', 512)->nullable();
                $table->string('link_status', 20)->nullable();
                $table->unsignedBigInteger('content_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('channel_poster_publish_logs')) {
            Schema::table('channel_poster_publish_logs', function (Blueprint $table) {
                if (Schema::hasColumn('channel_poster_publish_logs', 'post_url')) {
                    $table->dropColumn(['post_url', 'link_status', 'content_id']);
                }
            });
        }

        if (Schema::hasTable('channel_poster_queue')) {
            Schema::table('channel_poster_queue', function (Blueprint $table) {
                if (Schema::hasColumn('channel_poster_queue', 'content_id')) {
                    $table->dropColumn('content_id');
                    try {
                        $table->dropIndex('cp_queue_content_index');
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            });
        }

        Schema::dropIfExists('content_sync_admins');
        Schema::dropIfExists('content_sync_admin_prefs');
        Schema::dropIfExists('content_sync_operations');
        Schema::dropIfExists('content_sync_versions');
        Schema::dropIfExists('content_sync_contents');
    }
};
