<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait for RSS pipeline tests on in-memory SQLite.
 *
 * Mirrors UsesChannelPosterSqlite::createApplication() so the same tests can
 * combine both table sets (e.g. RssRecoveryTest) via `insteadof`.
 *
 * All fixture helpers use DB::table()->insertGetId() on purpose:
 *  - RssItem / RssChannel models have NO $fillable/$guarded, so ::create() fails;
 *  - RssPostItemTranslation / RssPostItemTranslationQueue have boot `created`
 *    hooks that dispatch jobs — raw inserts bypass them (tests invoke jobs
 *    explicitly when needed).
 */
trait UsesRssPipelineSqlite
{
    private array $savedAdminEnv = [];

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', false);
        $app->make('db')->purge();

        return $app;
    }

    /**
     * Tables required by the RSS pipeline (feeds, posts, translations, queues,
     * channels/origins) plus Spatie tag tables (RssHelper::createMessage reads
     * $post->rssItem->tags outside the job's try block).
     */
    protected function setUpRssPipelineTables(): void
    {
        Schema::dropIfExists('rss_post_item_translation_queues');
        Schema::dropIfExists('rss_post_item_translations');
        Schema::dropIfExists('rss_post_items');
        Schema::dropIfExists('rss_channel_origins');
        Schema::dropIfExists('rss_channels');
        Schema::dropIfExists('rss_items');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id');
            $table->morphs('taggable');
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });

        Schema::create('rss_channel_origins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('rss_channels', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('token');
            $table->string('target_id');
            $table->string('type', 20)->default('unknown');
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->text('sign')->nullable();
            $table->boolean('has_command')->default(0);
            $table->timestamps();
        });

        Schema::create('rss_items', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->string('url')->nullable();
            $table->string('url_ifttt')->nullable();
            $table->string('url_rss_dot_app')->nullable();
            $table->boolean('is_active')->default(1);
            $table->string('unique_xml_tag', 20)->nullable();
            $table->string('locale', 5)->default('fa');
            $table->string('target_locale', 5)->default('fa');
            $table->bigInteger('rss_channel_id')->default(1);
            $table->bigInteger('rss_business_id')->nullable()->default(1);
            $table->dateTime('last_synced_at')->nullable();
            $table->integer('interval_minutes')->default(59);
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('rss_post_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('rss_item_id');
            $table->string('title', 1000)->nullable();
            $table->string('link', 1000)->unique();
            $table->text('description')->nullable();
            // Mirrors database/migrations/2024_06_21_015417_add_image_to_rss_post_items_table.php.
            // RssService::readRssAndSave() always inserts image_url, so the fixture
            // must expose it or every item insert fails with "no column named image_url".
            $table->string('image_url', 2048)->nullable();
            $table->dateTime('pub_date')->nullable();
            $table->timestamps();
        });

        Schema::create('rss_post_item_translations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('rss_post_item_id');
            $table->bigInteger('author_chat_id')->nullable();
            $table->string('locale', 8)->default('fa');
            $table->string('title', 1000);
            $table->text('content');
            $table->bigInteger('approved_by_chat_id')->nullable();
            $table->boolean('approved')->default(0);
            $table->timestamps();
        });

        Schema::create('rss_post_item_translation_queues', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('rss_post_item_translation_id');
            $table->bigInteger('rss_channel_id');
            $table->string('status', 20)->default('queue');
            $table->string('message_id', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->string('media_error', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Admin env keys (AdminHelper::isAdmin) — save+neutralize so tests can
     * assert that execute is refused without a real admin, and can then
     * enable a known admin id explicitly via setAdminEnv().
     */
    public static function adminEnvKeys(): array
    {
        return [
            'CHAT_ID_ACCOUNT_1_SABER',
            'CHAT_ID_ACCOUNT_2_SABER',
            'SUPER_ADMIN_CHAT_ID_TELEGRAM',
            'SUPER_ADMIN_CHAT_ID_BALE',
            'SUPER_ADMIN_CHAT_ID_BALE2',
            'SUPER_ADMIN_CHAT_ID_GAP',
        ];
    }

    protected function neutralizeAdminEnv(): void
    {
        foreach (self::adminEnvKeys() as $key) {
            $this->savedAdminEnv[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key),
            ];
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    /**
     * Laravel 10 Env adapters read superglobals live (no per-key cache), so a
     * plain superglobal put is enough to make AdminHelper::isAdmin() see it.
     */
    protected function setAdminEnv(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    protected function restoreAdminEnv(): void
    {
        foreach ($this->savedAdminEnv as $key => $v) {
            if ($v['env'] !== null) {
                $_ENV[$key] = $v['env'];
            } else {
                unset($_ENV[$key]);
            }
            if ($v['server'] !== null) {
                $_SERVER[$key] = $v['server'];
            } else {
                unset($_SERVER[$key]);
            }
            if ($v['getenv'] !== false) {
                putenv($key . '=' . $v['getenv']);
            } else {
                putenv($key);
            }
        }
        $this->savedAdminEnv = [];
    }

    /**
     * Insert an rss_items row. Returns the new id.
     */
    protected function createRssFeed(array $attrs = []): int
    {
        return DB::table('rss_items')->insertGetId(array_merge([
            'title' => 'Test Feed',
            'description' => null,
            'url' => 'https://feed.example.com/rss.xml',
            'is_active' => 1,
            'unique_xml_tag' => null,
            'locale' => 'fa',
            'target_locale' => 'fa',
            'rss_channel_id' => 1,
            'rss_business_id' => null,
            'last_synced_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
            'interval_minutes' => 59,
            'consecutive_failures' => 0,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    /**
     * Insert an origin + channel pair. Returns the channel id.
     */
    protected function createRssChannel(string $mediumSlug = 'bale'): int
    {
        $originId = DB::table('rss_channel_origins')->insertGetId([
            'name' => 'Origin ' . $mediumSlug,
            'slug' => $mediumSlug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('rss_channels')->insertGetId([
            'title' => 'Test Channel ' . $mediumSlug,
            'slug' => $mediumSlug,
            'token' => '111111:TEST-' . $mediumSlug . '-token',
            'target_id' => '888' . str_pad((string) $originId, 3, '0', STR_PAD_LEFT),
            'type' => 'channel',
            'origin_id' => $originId,
            'sign' => null,
            'has_command' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert an rss_post_items row. Returns the new id.
     */
    protected function createRssPostItem(int $rssItemId, array $attrs = []): int
    {
        return DB::table('rss_post_items')->insertGetId(array_merge([
            'rss_item_id' => $rssItemId,
            'title' => 'Test Post Title',
            'link' => 'https://example.com/post-' . uniqid(),
            'description' => 'Post description',
            'image_url' => 'https://cdn.example.com/img.jpg',
            'pub_date' => now()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    /**
     * Insert an rss_post_item_translations row. Returns the new id.
     */
    protected function createRssTranslation(int $rssPostItemId, array $attrs = []): int
    {
        return DB::table('rss_post_item_translations')->insertGetId(array_merge([
            'rss_post_item_id' => $rssPostItemId,
            'author_chat_id' => null,
            'locale' => 'fa',
            'title' => 'Test Post Title',
            'content' => 'Translated content body.',
            'approved_by_chat_id' => null,
            'approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    /**
     * Insert an rss_post_item_translation_queues row. Returns the new id.
     */
    protected function createRssQueueRow(int $translationId, int $channelId, array $attrs = []): int
    {
        return DB::table('rss_post_item_translation_queues')->insertGetId(array_merge([
            'rss_post_item_translation_id' => $translationId,
            'rss_channel_id' => $channelId,
            'status' => 'queue',
            'message_id' => null,
            'sent_at' => null,
            'attempts' => 0,
            'last_error' => null,
            'media_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }
}
