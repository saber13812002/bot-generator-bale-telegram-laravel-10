<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterPublishLog;
use App\Models\ChannelPosterQueue;
use App\Models\ContentSyncAdmin;
use App\Models\ContentSyncAdminPrefs;
use App\Models\ContentSyncContent;
use App\Models\ContentSyncOperation;
use App\Models\ContentSyncVersion;
use App\Services\ChannelPosterQueuePublishService;
use App\Http\Controllers\ChannelPosterBotController;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * تست‌های جریان همگام‌سازی چندکاناله (fast mode) از طریق وب‌هوک واقعی.
 *
 * سناریوهای اصلی مأموریت:
 *  - لینک پیام + متن → انتشار در کانال‌های مجاز دیگر، بدون سؤال اضافه
 *  - ثبت post_url + link_status در دیتابیس
 *  - dedup لینک تکراری
 *  - گزارش /sync و /last
 *  - حالت صف و تکرار /retry
 *  - چند ادمین
 */
class ContentSyncFlowTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    private string $token = '123:channel-poster-token';

    private string $ownerChatId = '111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();

        $this->publisher = new FakeChannelPosterPublisher();
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );
    }

    /* ------------------------------------------------------------------ */

    public function test_link_then_text_publishes_to_other_platforms_with_stored_urls(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // مرحله ۱: لینک پیام منتشرشده در ایتا
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->assertStringContainsString(
            trans('bot.channel_sync_link_registered', ['id' => 1]),
            (string) $this->publisher->lastPrivateText()
        );

        $state = $this->ownerState($bot->id);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TEXT, $state->state);

        // مرحله ۲: متن مطلب
        $this->postText($this->ownerChatId, 'متن کامل مطلب برای انتشار');

        // انتشار فقط به بله و تلگرام (کانال مبدأ ایتا مستثنی)
        $this->assertCount(2, $this->publisher->publishes);
        $platforms = array_column($this->publisher->publishes, 'platform');
        sort($platforms);
        $this->assertSame(['bale', 'telegram'], $platforms);

        // تأیید کوتاه fast mode — بدون سؤال اضافی و آماده لینک بعدی
        $confirmation = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_sync_registered', ['id' => 1]), $confirmation);
        $this->assertStringContainsString('در بله ✅', $confirmation);
        $this->assertStringContainsString('در تلگرام ✅', $confirmation);
        $this->assertStringContainsString(trans('bot.channel_sync_ready'), $confirmation);

        // state پاک شده
        $this->assertNull($this->ownerState($bot->id));

        // نسخه‌ها: مبدأ (ایتا) + بله + تلگرام
        $this->assertSame(3, ContentSyncVersion::count());

        $content = ContentSyncContent::find(1);
        $this->assertSame('https://eitaa.com/alirezamehran_3/1182', $content->source_url);
        $this->assertSame('متن کامل مطلب برای انتشار', $content->text);
        $this->assertNotNull($content->text_hash);

        $bale = ContentSyncVersion::where('content_id', 1)->where('platform', 'bale')->first();
        $this->assertSame('published', $bale->publish_status);
        $this->assertNotNull($bale->destination_id);
        $this->assertSame('fake-msg-1', $bale->message_id);
        $this->assertSame('https://ble.ir/stackoverflow_com/8995754940207167599/fake-msg-1', $bale->post_url);
        $this->assertSame('constructed', $bale->link_status);
        $this->assertNotNull($bale->published_at);

        $telegram = ContentSyncVersion::where('content_id', 1)->where('platform', 'telegram')->first();
        $this->assertSame('published', $telegram->publish_status);
        $this->assertSame('https://t.me/saberblog/fake-msg-2', $telegram->post_url);
        $this->assertSame('confirmed', $telegram->link_status);

        $source = ContentSyncVersion::where('content_id', 1)->where('platform', 'eitaa')->first();
        $this->assertSame('published', $source->publish_status);
        $this->assertSame('confirmed', $source->link_status);
        $this->assertSame('https://eitaa.com/alirezamehran_3/1182', $source->post_url);

        // لاگ انتشار با post_url + content_id
        $this->assertSame(2, ChannelPosterPublishLog::count());
        $log = ChannelPosterPublishLog::where('content_id', 1)->where('platform', 'telegram')->first();
        $this->assertNotNull($log);
        $this->assertSame('https://t.me/saberblog/fake-msg-2', $log->post_url);
        $this->assertSame('confirmed', $log->link_status);

        // تاریخچه عملیات
        $ops = ContentSyncOperation::where('content_id', 1)->pluck('operation')->all();
        $this->assertContains(ContentSyncOperation::OP_REGISTER, $ops);
        $this->assertContains(ContentSyncOperation::OP_ATTACH_TEXT, $ops);
        $this->assertContains(ContentSyncOperation::OP_PUBLISH, $ops);
    }

    public function test_duplicate_link_is_rejected_without_republish(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن اول');
        $this->assertCount(2, $this->publisher->publishes);

        // ارسال دوباره همان لینک
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->assertStringContainsString(
            trans('bot.channel_sync_duplicate', ['id' => 1, 'url' => 'https://eitaa.com/alirezamehran_3/1182']),
            (string) $this->publisher->lastPrivateText()
        );

        $this->assertSame(1, ContentSyncContent::count());
        $this->assertCount(2, $this->publisher->publishes); // انتشار مجدد نشده
    }

    public function test_sync_report_shows_versions_and_link_status(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن گزارش');

        // /sync با id
        $this->postText($this->ownerChatId, '/sync 1');
        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_sync_report_header', ['id' => 1]), $report);
        $this->assertStringContainsString('https://eitaa.com/alirezamehran_3/1182', $report);
        $this->assertStringContainsString('https://ble.ir/stackoverflow_com/8995754940207167599/fake-msg-1', $report);
        $this->assertStringContainsString('https://t.me/saberblog/fake-msg-2', $report);
        $this->assertStringContainsString(trans('bot.channel_sync_link_confirmed'), $report);
        $this->assertStringContainsString(trans('bot.channel_sync_link_constructed'), $report);

        // /sync با لینک
        $this->postText($this->ownerChatId, '/sync https://eitaa.com/alirezamehran_3/1182');
        $this->assertStringContainsString(trans('bot.channel_sync_report_header', ['id' => 1]), (string) $this->publisher->lastPrivateText());

        // /last → آخرین مطلب
        $this->postText($this->ownerChatId, '/last');
        $this->assertStringContainsString(trans('bot.channel_sync_report_header', ['id' => 1]), (string) $this->publisher->lastPrivateText());

        // /sync بدون آرگومان → آخرین مطلب
        $this->postText($this->ownerChatId, '/sync');
        $this->assertStringContainsString(trans('bot.channel_sync_report_header', ['id' => 1]), (string) $this->publisher->lastPrivateText());

        // /sync با id ناموجود
        $this->postText($this->ownerChatId, '/sync 999');
        $this->assertStringContainsString(trans('bot.channel_sync_not_found'), (string) $this->publisher->lastPrivateText());
    }

    public function test_ambiguous_tags_asks_then_publishes_on_callback(): void
    {
        $bot = $this->createBot();
        // دو تگ متفاوت و کانال مبدأ ثبت‌نشده → ابهام
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '111111',
            'channel_title' => 'Bale News',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'telegram',
            'channel_chat_id' => '@saberblog',
            'channel_title' => 'Saber Blog',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'eitaa_other',
            'channel_title' => 'Eitaa Other',
            'tag' => 'tag2',
            'is_active' => true,
        ]);

        $this->postText($this->ownerChatId, 'https://eitaa.com/otherchannel/55');
        $this->postText($this->ownerChatId, 'متن ابهام‌دار');

        // از تگ پرسیده شده و هنوز منتشر نشده
        $this->assertStringContainsString(trans('bot.channel_sync_ask_tag'), (string) $this->publisher->lastPrivateText());
        $this->assertSame([], $this->publisher->publishes);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TAG, $this->ownerState($bot->id)->state);

        // انتخاب تگ tag1 از دکمه (index 0)
        $this->postCallback('cps:tag:0');

        $this->assertCount(2, $this->publisher->publishes);
        $this->assertSame(['bale', 'telegram'], array_column($this->publisher->publishes, 'platform'));
        $this->assertSame(
            'published',
            ContentSyncVersion::where('content_id', 1)->where('platform', 'telegram')->value('publish_status')
        );
    }

    public function test_queue_mode_enqueues_and_publish_item_updates_versions(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // تغییر حالت به صف از دکمه‌ها
        $this->postCallback('cps:mode:queue');
        $this->assertStringContainsString(trans('bot.channel_sync_mode_set', ['mode' => 'queue']), (string) $this->publisher->lastPrivateText());
        $this->assertSame('queue', ContentSyncAdminPrefs::getMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن در صف');

        // در صف قرار گرفت، بدون انتشار فوری
        $this->assertSame([], $this->publisher->publishes);
        // ساعت صف متغیر است؛ فقط پیشوند پایدار و پیام آماده‌بودن چک می‌شود
        $this->assertStringContainsString('⏳ مطلب 1 در صف انتشار', (string) $this->publisher->lastPrivateText());
        $this->assertStringContainsString(trans('bot.channel_sync_ready'), (string) $this->publisher->lastPrivateText());

        $queue = ChannelPosterQueue::first();
        $this->assertNotNull($queue);
        $this->assertSame(1, (int) $queue->content_id);
        $this->assertSame('pending', $queue->status);

        // نسخه‌ها در وضعیت queued
        $this->assertSame('queued', ContentSyncVersion::where('content_id', 1)->where('platform', 'bale')->value('publish_status'));
        $this->assertSame('queued', ContentSyncVersion::where('content_id', 1)->where('platform', 'telegram')->value('publish_status'));

        // پردازش صف
        $service = $this->app->make(ChannelPosterQueuePublishService::class);
        $result = $service->publishItem($queue->fresh(), false);

        $this->assertSame('published', $result['status']);
        $this->assertSame('published', $queue->fresh()->status);

        $bale = ContentSyncVersion::where('content_id', 1)->where('platform', 'bale')->first();
        $this->assertSame('published', $bale->publish_status);
        $this->assertNotNull($bale->post_url);
        $this->assertNotNull($bale->message_id);
        $this->assertStringStartsWith('https://ble.ir/stackoverflow_com/8995754940207167599/', $bale->post_url);

        $telegram = ContentSyncVersion::where('content_id', 1)->where('platform', 'telegram')->first();
        $this->assertSame('published', $telegram->publish_status);
        $this->assertStringStartsWith('https://t.me/saberblog/', $telegram->post_url);
    }

    public function test_soroush_destination_is_skipped_with_clear_reason(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'soroush',
            'channel_chat_id' => 'soroush_channel',
            'channel_title' => 'Soroush Channel',
            'tag' => 'tag1',
            'is_active' => true,
        ]);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن با سروش');

        // سروش publisher ندارد — ارسال نمی‌شود ولی نسخه ثبت می‌شود
        $platforms = array_column($this->publisher->publishes, 'platform');
        $this->assertNotContains('soroush', $platforms);

        $soroush = ContentSyncVersion::where('content_id', 1)->where('platform', 'soroush')->first();
        $this->assertNotNull($soroush);
        $this->assertSame('skipped', $soroush->publish_status);
        $this->assertSame(trans('bot.channel_sync_soroush_not_ready'), $soroush->last_error);
        $this->assertNull($soroush->post_url);

        $this->assertStringContainsString('در سروش ⚠️', (string) $this->publisher->lastPrivateText());
    }

    public function test_failed_publish_can_be_retried(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);
        $this->publisher->failPlatforms = ['bale'];

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن با خطا در بله');

        $bale = ContentSyncVersion::where('content_id', 1)->where('platform', 'bale')->first();
        $this->assertSame('failed', $bale->publish_status);
        $this->assertStringContainsString('در بله ❌', (string) $this->publisher->lastPrivateText());

        // حالا بله درست می‌شود و /retry
        $this->publisher->failPlatforms = [];
        $this->postText($this->ownerChatId, '/retry');

        $bale->refresh();
        $this->assertSame('published', $bale->publish_status);
        $this->assertNotNull($bale->post_url);
        $this->assertStringContainsString('در بله ✅', (string) $this->publisher->lastPrivateText());

        // عملیات retry ثبت شده
        $this->assertContains(ContentSyncOperation::OP_RETRY, ContentSyncOperation::where('content_id', 1)->pluck('operation')->all());
    }

    public function test_retry_with_no_failures_says_nothing_to_retry(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن بدون خطا');

        $this->postText($this->ownerChatId, '/retry');
        $this->assertStringContainsString(
            trans('bot.channel_sync_retry_nothing', ['id' => 1]),
            (string) $this->publisher->lastPrivateText()
        );
    }

    public function test_owner_can_add_admin_and_admin_uses_sync_flow(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // ادمین دوم توسط مالک اضافه می‌شود
        $this->postText($this->ownerChatId, '/admin 222');
        $this->assertStringContainsString(trans('bot.channel_sync_admin_added', ['chat_id' => '222']), (string) $this->publisher->lastPrivateText());
        $this->assertTrue(ContentSyncAdmin::isRegistered($bot->id, '222', 'bale'));

        // /admin بدون آرگومان → لیست
        $this->postText($this->ownerChatId, '/admin');
        $this->assertStringContainsString('222', (string) $this->publisher->lastPrivateText());

        // /admin آرگومان نامعتبر
        $this->postText($this->ownerChatId, '/admin abc');
        $this->assertStringContainsString(trans('bot.channel_sync_admin_invalid'), (string) $this->publisher->lastPrivateText());

        // ادمین دوم لینک می‌فرستد و جریان کامل کار می‌کند
        $this->postText('222', 'https://eitaa.com/alirezamehran_3/1182');
        $this->assertStringContainsString(trans('bot.channel_sync_link_registered', ['id' => 1]), (string) $this->publisher->lastPrivateText());
        $this->postText('222', 'متن ادمین دوم');
        $this->assertCount(2, $this->publisher->publishes);

        // ادمین نمی‌تواند ادمین اضافه کند
        $this->postText('222', '/admin 333');
        $this->assertStringContainsString(trans('bot.channel_poster_not_owner'), (string) $this->publisher->lastPrivateText());
        $this->assertFalse(ContentSyncAdmin::isRegistered($bot->id, '333', 'bale'));
    }

    public function test_non_admin_cannot_use_sync(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText('333', 'https://eitaa.com/alirezamehran_3/1182');
        $this->assertStringContainsString(trans('bot.channel_poster_not_owner'), (string) $this->publisher->lastPrivateText());
        $this->assertSame(0, ContentSyncContent::count());
        $this->assertSame([], $this->publisher->publishes);

        $this->postText('333', '/sync 1');
        $this->assertStringContainsString(trans('bot.channel_poster_not_owner'), (string) $this->publisher->lastPrivateText());
    }

    public function test_invalid_url_is_rejected(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // لینک غیرمجاز → جریان sync شروع نمی‌شود و به دستِ قدیمی (unsupported) برمی‌گردد
        $this->postText($this->ownerChatId, 'https://example.com/channel/123');
        $this->assertSame(0, ContentSyncContent::count());
        $this->assertSame([], $this->publisher->publishes);
    }

    public function test_link_without_any_destination_asks_to_connect_channel(): void
    {
        $bot = $this->createBot();
        // هیچ مقصدی ثبت نشده

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->assertStringContainsString(
            trans('bot.channel_sync_link_registered_ask_forward', ['id' => 1]),
            (string) $this->publisher->lastPrivateText()
        );
        $this->assertSame(1, ContentSyncContent::count());
    }

    public function test_new_command_resets_sync_state(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TEXT, $this->ownerState($bot->id)->state);

        $this->postText($this->ownerChatId, '/new');
        $this->assertStringContainsString(trans('bot.channel_sync_ready'), (string) $this->publisher->lastPrivateText());
        $this->assertNull($this->ownerState($bot->id));
    }

    public function test_long_text_is_chunked_per_destination(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // متن باید از حد مجاز (3500 کاراکتر) بیشتر باشد تا قطعه‌بندی انجام شود
        $longText = implode("\n", array_map(fn ($i) => 'خط شماره ' . $i, range(1, 400)));
        $this->assertGreaterThan(3500, mb_strlen($longText));
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, $longText);

        // هر مقصد چند قطعه دریافت کرده (هر chunk یک publish است)
        $balePublishes = array_filter($this->publisher->publishes, fn ($p) => $p['platform'] === 'bale');
        $this->assertGreaterThan(1, count($balePublishes));
        foreach ($balePublishes as $p) {
            $this->assertLessThanOrEqual(3500, mb_strlen((string) $p['text']));
        }

        // مجموع قطعه‌ها همان متن اصلی است
        $joined = implode("\n", array_map(fn ($p) => (string) $p['text'], $balePublishes));
        $this->assertSame($longText, $joined);

        $baleVersion = ContentSyncVersion::where('content_id', 1)->where('platform', 'bale')->first();
        $this->assertSame(count($balePublishes), (int) $baleVersion->chunks_count);
        $this->assertSame('published', $baleVersion->publish_status);
    }

    public function test_second_link_after_fast_cycle_starts_new_content(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'مطلب اول');

        // لینک بعدی بلافاصله پس از چرخه اول
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1183');
        $this->assertStringContainsString(
            trans('bot.channel_sync_link_registered', ['id' => 2]),
            (string) $this->publisher->lastPrivateText()
        );
        $this->postText($this->ownerChatId, 'مطلب دوم');

        $this->assertSame(2, ContentSyncContent::count());
        $this->assertSame(
            'published',
            ContentSyncVersion::where('content_id', 2)->where('platform', 'telegram')->value('publish_status')
        );
    }

    public function test_link_sent_while_awaiting_text_starts_new_content(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // لینک اول ثبت می‌شود و ربات در حالت «انتظار متن» قرار می‌گیرد
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $state = $this->ownerState($bot->id);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TEXT, $state->state);

        // به‌جای متن، لینک پیام جدید می‌آید — نباید «متن مطلب ۱» قلمداد شود
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1183');
        $this->assertStringContainsString(
            trans('bot.channel_sync_link_registered', ['id' => 2]),
            (string) $this->publisher->lastPrivateText()
        );

        // محتوای ۱ هنوز بدون متن مانده و محتوا ۲ ثبت شده
        $this->assertNull(ContentSyncContent::find(1)->text);
        $content2 = ContentSyncContent::find(2);
        $this->assertNotNull($content2);
        $this->assertSame('https://eitaa.com/alirezamehran_3/1183', $content2->source_url);

        // هنوز در انتظار متن (این‌بار برای محتوا ۲)
        $state = $this->ownerState($bot->id);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TEXT, $state->state);
        $this->assertSame(2, (int) $state->getData('content_id'));
    }

    public function test_channels_command_lists_destinations_and_unlink_button_deactivates(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // فهرست کانال‌های متصل با دکمه حذف
        $this->postText($this->ownerChatId, '/channels');
        $last = array_key_last($this->publisher->privateMessages);
        $this->assertStringContainsString(
            trans('bot.channel_sync_channels_header'),
            $this->publisher->privateMessages[$last]['text']
        );
        $keyboard = $this->publisher->privateMessages[$last]['keyboard'] ?? [];
        $this->assertIsArray($keyboard);
        $this->assertCount(3, $keyboard);

        $callbackData = $keyboard[0][0]['callback_data'];
        $this->assertMatchesRegularExpression('#^cp:unlink:\d+$#', $callbackData);
        $destId = (int) substr($callbackData, 10);

        // حذف کانال — ارتباط آن با تگ قطع می‌شود
        $this->postCallback($callbackData);
        $total = count($this->publisher->privateMessages);
        // پیام حذف + فهرست به‌روز دوباره ارسال می‌شود
        $label = 'eitaa — Alireza (tag1)';
        $this->assertStringContainsString(
            trans('bot.channel_sync_channel_unlinked', ['channel' => $label]),
            $this->publisher->privateMessages[$total - 2]['text']
        );
        $this->assertStringContainsString(
            trans('bot.channel_sync_channels_header'),
            $this->publisher->privateMessages[$total - 1]['text']
        );

        $this->assertFalse((bool) ChannelPosterDestination::find($destId)->is_active);

        // شمارش تگ در /tags به ۲ کاهش پیدا می‌کند
        $this->postText($this->ownerChatId, '/tags');
        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString('tag1 (2)', $report);

        // در لیست /channels هم فقط دو کانال باقی مانده
        $this->postText($this->ownerChatId, '/channels');
        $last = array_key_last($this->publisher->privateMessages);
        $keyboard = $this->publisher->privateMessages[$last]['keyboard'] ?? [];
        $this->assertCount(2, (array) $keyboard);
    }

    public function test_unlink_unknown_destination_is_handled_gracefully(): void
    {
        $bot = $this->createBot();

        $this->postCallback('cp:unlink:999');
        $this->assertStringContainsString(
            trans('bot.channel_sync_channels_gone'),
            (string) $this->publisher->lastPrivateText()
        );
    }

    public function test_mode_command_shows_current_mode_with_keyboard(): void
    {
        $bot = $this->createBot();

        $this->postText($this->ownerChatId, '/mode');
        $last = array_key_last($this->publisher->privateMessages);
        $this->assertStringContainsString(
            trans('bot.channel_sync_mode_current', ['mode' => 'immediate']),
            $this->publisher->privateMessages[$last]['text']
        );
        $keyboard = $this->publisher->privateMessages[$last]['keyboard'] ?? null;
        $this->assertIsArray($keyboard);
        $this->assertNotEmpty($keyboard);
    }

    public function test_tags_command_lists_tags(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن برای تگ');

        $this->postText($this->ownerChatId, '/tags');
        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_sync_tags_header'), $report);
        $this->assertStringContainsString('tag1', $report);
        $this->assertStringContainsString(trans('bot.channel_sync_last_tag', ['tag' => 'tag1']), $report);
    }

    /* ------------------------------------------------------------------ */

    private function createBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_token' => $this->token,
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => (int) $this->ownerChatId,
            'bale_webhook_is_set' => true,
        ]);
    }

    /**
     * سه مقصد با تگ مشترک tag1: ایتا (مبدأ تست‌ها) + بله + تلگرام عمومی.
     */
    private function createDestinations(int $botId): void
    {
        ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_3',
            'channel_title' => 'Alireza',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'telegram',
            'channel_chat_id' => '@saberblog',
            'channel_title' => 'Saber Blog',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
    }

    private function postText(string $chatId, string $text): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            [
                'update_id' => rand(1, 999999),
                'message' => [
                    'message_id' => rand(1, 999999),
                    'from' => ['id' => (int) $chatId, 'is_bot' => false],
                    'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                    'date' => time(),
                    'text' => $text,
                ],
            ]
        )->assertOk();
    }

    private function postCallback(string $data): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            [
                'update_id' => rand(1, 999999),
                'callback_query' => [
                    'id' => 'cb-' . md5($data),
                    'from' => ['id' => (int) $this->ownerChatId],
                    'data' => $data,
                    'message' => [
                        'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                    ],
                ],
            ]
        )->assertOk();
    }

    private function ownerState(int $botId): ?BotUserState
    {
        $botUser = \App\Models\BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $botId)->first();

        return $botUser ? BotUserState::where('bot_user_id', $botUser->id)->first() : null;
    }
}
