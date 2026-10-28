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

        // گزارش fast mode — بدون سؤال اضافی + آماده لینک بعدی + فوتر راهنما
        $confirmation = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_sync_report_header', ['id' => 1]), $confirmation);
        $this->assertStringContainsString(trans('bot.channel_sync_report_content_id', ['id' => 1]), $confirmation);
        $this->assertStringContainsString('در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_published'), $confirmation);
        $this->assertStringContainsString('در تلگرام (Saber Blog) — ' . trans('bot.channel_sync_report_status_published'), $confirmation);
        $this->assertStringContainsString(trans('bot.channel_sync_report_status_existing_source'), $confirmation);
        $this->assertStringContainsString(trans('bot.channel_sync_ready'), $confirmation);
        $this->assertStringContainsString(trans('bot.channel_sync_help_footer'), $confirmation);

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
        $this->assertSame(ContentSyncVersion::STATUS_EXISTING_SOURCE, $source->publish_status);
        $this->assertSame('confirmed', $source->link_status);
        $this->assertSame('https://eitaa.com/alirezamehran_3/1182', $source->post_url);
        $this->assertSame('1182', $source->message_id);

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

        // تغییر حالت به صف از دکمه‌ها — فقط toast ثبت، بدون پیام جدید
        $before = count($this->publisher->privateMessages);
        $this->postCallback('cps:mode:queue');
        $this->assertCount($before, $this->publisher->privateMessages);
        $lastCb = $this->publisher->callbacks[array_key_last($this->publisher->callbacks)];
        $this->assertStringContainsString(
            trans('bot.channel_sync_settings_saved', ['label' => trans('bot.channel_sync_mode_queue')]),
            $lastCb['text']
        );
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

    public function test_soroush_destination_is_published_like_other_platforms(): void
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

        // Phase 3: سروش دیگر skip نمی‌شود — مثل بقیه پلتفرم‌ها publish می‌شود
        $platforms = array_column($this->publisher->publishes, 'platform');
        $this->assertContains('soroush', $platforms);

        $soroush = ContentSyncVersion::where('content_id', 1)->where('platform', 'soroush')->first();
        $this->assertNotNull($soroush);
        $this->assertSame('published', $soroush->publish_status);
        $this->assertNotNull($soroush->message_id);
        // فرمت لینک پست سروش در دسترس نیست (unverified) — post_url نمی‌سازد
        $this->assertNull($soroush->post_url);

        $this->assertStringContainsString('در سروش (Soroush Channel) — ' . trans('bot.channel_sync_report_status_published'), (string) $this->publisher->lastPrivateText());
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
        $this->assertStringContainsString('در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_failed'), (string) $this->publisher->lastPrivateText());

        // حالا بله درست می‌شود و /retry
        $this->publisher->failPlatforms = [];
        $this->postText($this->ownerChatId, '/retry');

        $bale->refresh();
        $this->assertSame('published', $bale->publish_status);
        $this->assertNotNull($bale->post_url);
        $this->assertStringContainsString('در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_published'), (string) $this->publisher->lastPrivateText());

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
        $balePublishes = array_values(array_filter($this->publisher->publishes, fn ($p) => $p['platform'] === 'bale'));
        $this->assertGreaterThan(1, count($balePublishes));
        foreach ($balePublishes as $p) {
            $this->assertLessThanOrEqual(3500, mb_strlen((string) $p['text']));
        }

        // §4: امضای منبع به انتهای آخرین قطعه می‌چسبد (برچسب + لینک مبدأ)
        $label = trans('bot.channel_sync_source_label', [
            'channel' => 'Alireza',
            'platform' => trans('bot.channel_poster_sig_eitaa'),
        ]);
        $suffix = $label . "\n" . 'https://eitaa.com/alirezamehran_3/1182';
        $this->assertStringEndsWith($suffix, (string) $balePublishes[count($balePublishes) - 1]['text']);

        // مجموع قطعه‌ها (بدون امضای آخرین قطعه) همان متن اصلی است
        $texts = array_map(fn (array $p) => (string) $p['text'], $balePublishes);
        $needle = "\n\n" . $suffix;
        $last = $texts[count($texts) - 1];
        $texts[count($texts) - 1] = str_ends_with($last, $needle)
            ? mb_substr($last, 0, -mb_strlen($needle))
            : $last;
        $this->assertSame($longText, implode("\n", $texts));

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

    public function test_mode_command_shows_current_mode_read_only(): void
    {
        $bot = $this->createBot();

        $this->postText($this->ownerChatId, '/mode');
        $last = array_key_last($this->publisher->privateMessages);
        $this->assertStringContainsString(
            trans('bot.channel_sync_mode_current', [
                'mode' => trans('bot.channel_sync_mode_immediate'),
            ]),
            $this->publisher->privateMessages[$last]['text']
        );
        // /mode فقط نمایشی است؛ دکمه‌ای ندارد (تغییر از /settings)
        $this->assertNull($this->publisher->privateMessages[$last]['keyboard'] ?? null);
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

    /* =================== تست‌های فاز دوم: dedup + گزارش + تنظیمات =================== */

    public function test_single_source_channel_sends_nothing(): void
    {
        $bot = $this->createBot();
        // تنها مقصد، خودِ کانال مبدأ است
        $sourceDest = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_3',
            'channel_title' => 'Alireza',
            'tag' => 'tag1',
            'is_active' => true,
        ]);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن بدون مقصد');

        // هیچ جایی ارسال نشده
        $this->assertSame([], $this->publisher->publishes);
        $this->assertStringContainsString(trans('bot.channel_sync_no_targets'), (string) $this->publisher->lastPrivateText());

        // فقط نسخه‌ی خودِ مبدأ (existing_source) ثبت شده
        $this->assertSame(1, ContentSyncVersion::count());
        $source = ContentSyncVersion::first();
        $this->assertSame(ContentSyncVersion::STATUS_EXISTING_SOURCE, $source->publish_status);
        $this->assertSame($sourceDest->id, $source->destination_id);
        $this->assertSame('1182', $source->message_id);

        $this->assertNull($this->ownerState($bot->id));
    }

    public function test_two_eitaa_channels_second_channel_published(): void
    {
        $bot = $this->createBot();
        $sourceDest = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_3',
            'channel_title' => 'Alireza',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_4',
            'channel_title' => 'Alireza Four',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'tag' => 'tag1',
            'is_active' => true,
        ]);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن دوکاناله ایتا');

        // فقط کانال دومِ ایتا و بله انتشار می‌گیرند (ترتیب انتشار مهم نیست)
        $this->assertCount(2, $this->publisher->publishes);
        $channels = array_column($this->publisher->publishes, 'channel_chat_id');
        sort($channels);
        $this->assertSame(['8995754940207167599', 'alirezamehran_4'], $channels);

        // گزارش: تمایز کانال مبدأ (⏭) از کانال دوم (✅)
        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString('در ایتا (alirezamehran_3) — ' . trans('bot.channel_sync_report_status_existing_source'), $report);
        $this->assertStringContainsString('در ایتا (Alireza Four) — ' . trans('bot.channel_sync_report_status_published'), $report);
        $this->assertStringContainsString('در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_published'), $report);

        $this->assertSame(3, ContentSyncVersion::count());
        $source = ContentSyncVersion::where('platform', 'eitaa')->where('destination_id', $sourceDest->id)->first();
        $this->assertNotNull($source);
        $this->assertSame(ContentSyncVersion::STATUS_EXISTING_SOURCE, $source->publish_status);
        $this->assertSame('published', ContentSyncVersion::where('platform', 'eitaa')->where('channel_username', 'Alireza Four')->value('publish_status'));
    }

    public function test_two_telegram_channels_second_channel_published(): void
    {
        $bot = $this->createBot();
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
            'platform' => 'telegram',
            'channel_chat_id' => '@sabernews',
            'channel_title' => 'Saber News',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'channel_username' => 'stackoverflow_com',
            'tag' => 'tag1',
            'is_active' => true,
        ]);

        $this->postText($this->ownerChatId, 'https://t.me/saberblog/77');
        $this->postText($this->ownerChatId, 'متن دوکاناله تلگرام');

        // کانال مبدأ (@saberblog) دوباره ارسال نمی‌شود (ترتیب انتشار مهم نیست)
        $this->assertCount(2, $this->publisher->publishes);
        $channels = array_column($this->publisher->publishes, 'channel_chat_id');
        sort($channels);
        $this->assertSame(['8995754940207167599', '@sabernews'], $channels);

        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString('در تلگرام (saberblog) — ' . trans('bot.channel_sync_report_status_existing_source'), $report);
        $this->assertStringContainsString('در تلگرام (Saber News) — ' . trans('bot.channel_sync_report_status_published'), $report);
        $this->assertStringContainsString('در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_published'), $report);

        // §6: نسخه‌ی مبدأ به مقصدی با username دقیق گره خورده
        $source = ContentSyncVersion::where('platform', 'telegram')
            ->where('publish_status', ContentSyncVersion::STATUS_EXISTING_SOURCE)
            ->first();
        $this->assertNotNull($source);
        $this->assertSame(
            ChannelPosterDestination::where('channel_chat_id', '@saberblog')->value('id'),
            $source->destination_id
        );
        $this->assertSame('https://t.me/saberblog/77', $source->post_url);
    }

    public function test_visually_different_url_of_same_channel_is_not_republished(): void
    {
        $bot = $this->createBot();

        // بخش ۱: کانال خصوصی تلگرام — لینک c/{shortId} در مقابل chat id عددی ذخیره‌شده
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'telegram',
            'channel_chat_id' => '-1001234567890',
            'channel_title' => 'Private Channel',
            'tag' => 'tag1',
            'is_active' => true,
        ]);

        $this->postText($this->ownerChatId, 'https://t.me/c/1234567890/42');
        $this->postText($this->ownerChatId, 'متن کانال خصوصی');
        $this->assertSame([], $this->publisher->publishes);
        $this->assertStringContainsString(trans('bot.channel_sync_no_targets'), (string) $this->publisher->lastPrivateText());

        // بخش ۲: ایتا — لینک با upper/lower متفاوت برای همان username (بات جدا با توکن جدا)
        $bot2 = $this->createBot('456:second-channel-poster-token');
        ChannelPosterDestination::create([
            'bot_id' => $bot2->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'SomeUser',
            'channel_title' => 'Some User',
            'tag' => 'tag1',
            'is_active' => true,
        ]);

        $this->postText($this->ownerChatId, 'https://eitaa.com/SOMEUSER/5', '456:second-channel-poster-token');
        $this->postText($this->ownerChatId, 'متن با اختلاف upper/lower', '456:second-channel-poster-token');

        $this->assertSame([], $this->publisher->publishes);
        $this->assertSame(2, ContentSyncContent::count());
        $this->assertSame(
            [ContentSyncVersion::STATUS_EXISTING_SOURCE, ContentSyncVersion::STATUS_EXISTING_SOURCE],
            ContentSyncVersion::orderBy('id')->pluck('publish_status')->all()
        );
    }

    public function test_platform_dedup_mode_excludes_whole_platform(): void
    {
        $bot = $this->createBot();
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_3',
            'channel_title' => 'Alireza',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_4',
            'channel_title' => 'Alireza Four',
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

        // فعال‌سازی «حذف تکراری: کل پیام‌رسان»
        $this->postCallback('cps:dedup:platform');
        $this->assertSame(ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM, ContentSyncAdminPrefs::getDedupMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن حذف تکراری کل پیام‌رسان');

        // هر دو کانال ایتا حذف می‌شوند؛ فقط تلگرام انتشار می‌گیرد
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(['telegram'], array_column($this->publisher->publishes, 'platform'));
        $this->assertSame(['@saberblog'], array_column($this->publisher->publishes, 'channel_chat_id'));

        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString('در ایتا (alirezamehran_3) — ' . trans('bot.channel_sync_report_status_existing_source'), $report);
        // §3: کانال دومِ ایتا قبل از فراخوانی API فیلتر شده و در گزارش fast-mode به‌صورت خط خلاصه آمده
        $this->assertStringContainsString(
            trans('bot.channel_poster_sig_eitaa') . ' (Alireza Four) — ' . trans('bot.channel_sync_report_reason_source_platform'),
            $report
        );
        $this->assertStringContainsString(trans('bot.channel_sync_report_reason_source_platform'), $report);

        $this->assertSame(2, ContentSyncVersion::count());
        $this->assertSame(1, ContentSyncVersion::where('platform', 'eitaa')->count());
    }

    public function test_settings_persist_per_admin_and_owner_default_inherited(): void
    {
        $bot = $this->createBot();

        $this->postText($this->ownerChatId, '/admin 222');
        $this->assertTrue(ContentSyncAdmin::isRegistered($bot->id, '222', 'bale'));

        // ادمین دوم تنظیمات شخصی خود را با دکمه‌ها تغییر می‌دهد — فقط toast ثبت، بدون پیام جدید
        $this->postCallbackAs('222', 'cps:mode:queue');
        $lastCb = $this->publisher->callbacks[array_key_last($this->publisher->callbacks)];
        $this->assertStringContainsString(
            trans('bot.channel_sync_settings_saved', ['label' => trans('bot.channel_sync_mode_queue')]),
            $lastCb['text']
        );

        $this->assertSame('queue', ContentSyncAdminPrefs::getMode($bot->id, '222', 'bale'));
        $this->assertSame('immediate', ContentSyncAdminPrefs::getMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postCallbackAs('222', 'cps:dedup:platform');
        $this->assertSame(ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM, ContentSyncAdminPrefs::getDedupMode($bot->id, '222', 'bale'));
        $this->assertSame(ContentSyncAdminPrefs::DEDUP_SOURCE_CHANNEL, ContentSyncAdminPrefs::getDedupMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postCallbackAs('222', 'cps:fast:off');
        $this->assertFalse(ContentSyncAdminPrefs::getFastMode($bot->id, '222', 'bale'));
        $this->assertTrue(ContentSyncAdminPrefs::getFastMode($bot->id, $this->ownerChatId, 'bale'));

        // پیش‌فرض مشترک (تنظیم‌شده توسط مالک) برای ادمین‌های جدید به ارث می‌رسد
        $this->postCallback('cps:default:dedup:platform');
        $this->assertSame(
            ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM,
            ContentSyncAdminPrefs::botDefaults($bot->id, 'bale')->dedup_mode
        );

        $this->postText($this->ownerChatId, '/admin 333');
        $this->assertSame(
            ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM,
            ContentSyncAdminPrefs::getDedupMode($bot->id, '333', 'bale')
        );
    }

    public function test_help_lists_active_commands_and_owner_section_is_conditional(): void
    {
        $bot = $this->createBot();
        $this->postText($this->ownerChatId, '/admin 222');

        // مالک: راهنمای کامل + دستورات مخصوص مالک
        $this->postText($this->ownerChatId, '/help');
        $ownerHelp = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_sync_help_title'), $ownerHelp);
        $this->assertStringContainsString(trans('bot.channel_sync_help_flow'), $ownerHelp);
        $this->assertStringContainsString(trans('bot.channel_sync_help_commands'), $ownerHelp);
        $this->assertStringContainsString(trans('bot.channel_sync_help_owner_commands'), $ownerHelp);

        // ادمین دوم: بدون بخش مالک
        $this->postText('222', '/help');
        $adminHelp = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_sync_help_commands'), $adminHelp);
        $this->assertStringNotContainsString(trans('bot.channel_sync_help_owner_commands'), $adminHelp);

        // غیرادمین: اجازه ندارند
        $this->postText('333', '/help');
        $this->assertStringContainsString(trans('bot.channel_poster_not_owner'), (string) $this->publisher->lastPrivateText());
    }

    public function test_failed_destination_does_not_corrupt_other_results(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);
        $this->publisher->failPlatforms = ['bale'];

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن با خطا در یکی از مقصدها');

        // بله ناموفق؛ تلگرام سالم منتشر شده
        $this->assertSame('failed', ContentSyncVersion::where('platform', 'bale')->value('publish_status'));
        $this->assertSame('published', ContentSyncVersion::where('platform', 'telegram')->value('publish_status'));
        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString('در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_failed'), $report);
        $this->assertStringContainsString('در تلگرام (Saber Blog) — ' . trans('bot.channel_sync_report_status_published'), $report);

        // خطا برطرف شد و /retry — فقط مقصدِ ناموفق دوباره ارسال می‌شود
        $this->publisher->failPlatforms = [];
        $this->postText($this->ownerChatId, '/retry');

        $this->assertSame('published', ContentSyncVersion::where('platform', 'bale')->value('publish_status'));
        $this->assertCount(1, array_filter($this->publisher->publishes, fn ($p) => $p['platform'] === 'telegram'));
        $this->assertCount(2, array_filter($this->publisher->publishes, fn ($p) => $p['platform'] === 'bale'));
        $this->assertStringContainsString(
            'در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_published'),
            (string) $this->publisher->lastPrivateText()
        );
    }

    public function test_reports_end_with_help_footer(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن فوتر');

        // تأیید fast mode با فوتر /help تمام می‌شود
        $this->assertStringEndsWith(trans('bot.channel_sync_help_footer'), (string) $this->publisher->lastPrivateText());

        // گزارش /sync و /last هم با همان فوتر تمام می‌شوند
        $this->postText($this->ownerChatId, '/sync 1');
        $this->assertStringEndsWith(trans('bot.channel_sync_help_footer'), (string) $this->publisher->lastPrivateText());

        $this->postText($this->ownerChatId, '/last');
        $this->assertStringEndsWith(trans('bot.channel_sync_help_footer'), (string) $this->publisher->lastPrivateText());
    }

    public function test_links_only_show_compact_settings_keyboard_once(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // §5: اولین لینک، کیبورد فشرده‌ی تنظیمات (۲×۲) را نشان می‌دهد
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $first = $this->publisher->privateMessages[array_key_last($this->publisher->privateMessages)];
        $this->assertStringContainsString(trans('bot.channel_sync_link_registered', ['id' => 1]), $first['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_ot_intro'), $first['text']);
        $this->assertIsArray($first['keyboard'] ?? null);
        $this->assertCount(2, $first['keyboard']);
        $this->assertCount(2, $first['keyboard'][0]);
        $this->assertCount(2, $first['keyboard'][1]);
        $this->assertTrue(ContentSyncAdminPrefs::isSettingsAsked($bot->id, $this->ownerChatId, 'bale'));

        // لینک دوم: کیبورد تکرار نمی‌شود
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1183');
        $second = $this->publisher->privateMessages[array_key_last($this->publisher->privateMessages)];
        $this->assertStringContainsString(trans('bot.channel_sync_link_registered', ['id' => 2]), $second['text']);
        $this->assertNull($second['keyboard'] ?? null);
    }

    // === تست‌های UX منوی جدید /settings (فاز ۳) ===

    /**
     * UX-1: اولین لینک مالک — پیام کوتاه سه‌خطی بدون هیچ دکمه‌ای.
     */
    public function test_ux_owner_first_link_shows_compact_keyboard_once(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];

        $this->assertStringContainsString('✅ لینک مطلب #1 ثبت شد.', $msg['text']);
        $this->assertStringContainsString('📝 لطفاً متن مطلب را ارسال کنید.', $msg['text']);
        $this->assertStringContainsString('⚙️ تغییر تنظیمات: /settings', $msg['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_ot_intro'), $msg['text']);

        // §5: کیبورد فشرده ۲×۲ با چهار گزینه‌ی اصلی
        $this->assertIsArray($msg['keyboard'] ?? null);
        $this->assertCount(2, $msg['keyboard']);
        $this->assertCount(2, $msg['keyboard'][0]);
        $this->assertCount(2, $msg['keyboard'][1]);
        $flat = array_merge($msg['keyboard'][0], $msg['keyboard'][1]);
        $this->assertSame(
            ['cps:sub:dedup', 'cps:sub:time', 'cps:sub:fast', 'cps:sub:attr'],
            array_map(fn (array $b) => $b['callback_data'], $flat)
        );
        $labels = implode(' | ', array_map(fn (array $b) => $b['text'], $flat));
        $this->assertStringContainsString(trans('bot.channel_sync_ot_dedup'), $labels);
        $this->assertStringContainsString(trans('bot.channel_sync_ot_time'), $labels);
        $this->assertStringContainsString(trans('bot.channel_sync_ot_fast'), $labels);
        $this->assertStringContainsString(trans('bot.channel_sync_ot_attr'), $labels);

        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TEXT, $this->ownerState($bot->id)->state);
        $this->assertTrue(ContentSyncAdminPrefs::isSettingsAsked($bot->id, $this->ownerChatId, 'bale'));
    }

    /**
     * UX-2: اولین لینک ادمین (غیرمالک) — بدون دکمه تنظیمات.
     */
    public function test_ux_admin_first_link_shows_compact_keyboard_once(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);
        $this->postText($this->ownerChatId, '/admin 222');
        $this->assertTrue(ContentSyncAdmin::isRegistered($bot->id, '222', 'bale'));

        $this->postText('222', 'https://eitaa.com/alirezamehran_3/1182');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];

        $this->assertSame('222', $msg['chat_id']);
        $this->assertStringContainsString('/settings', $msg['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_ot_intro'), $msg['text']);
        $this->assertIsArray($msg['keyboard'] ?? null);
        $this->assertCount(2, $msg['keyboard']);
        $flat = array_merge($msg['keyboard'][0], $msg['keyboard'][1]);
        $this->assertSame(
            ['cps:sub:dedup', 'cps:sub:time', 'cps:sub:fast', 'cps:sub:attr'],
            array_map(fn (array $b) => $b['callback_data'], $flat)
        );

        // سؤال یک‌بار برای هر ادمین جداگانه است
        $this->assertTrue(ContentSyncAdminPrefs::isSettingsAsked($bot->id, '222', 'bale'));
        $this->assertFalse(ContentSyncAdminPrefs::isSettingsAsked($bot->id, $this->ownerChatId, 'bale'));
    }

    /**
     * UX-3: متن بعد از لینک — مطابق تنظیمات فعلی منتشر می‌شود (منطق انتشار تغییر نکرده).
     */
    public function test_ux_text_after_link_publishes_per_current_settings(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن آزمایشی فاز ۳');

        $this->assertCount(2, $this->publisher->publishes);
        $platforms = array_map(fn (array $p) => $p['platform'], $this->publisher->publishes);
        sort($platforms);
        $this->assertSame(['bale', 'telegram'], $platforms); // ایتا مبدأ رد شد

        $last = array_key_last($this->publisher->privateMessages);
        $this->assertStringContainsString(trans('bot.channel_sync_report_header', ['id' => 1]), $this->publisher->privateMessages[$last]['text']);
    }

    /**
     * UX-4: منوی اصلی /settings — کوتاه و خوانا؛ هر دکمه در یک ردیف.
     */
    public function test_ux_settings_main_menu_is_short_with_one_button_per_row(): void
    {
        $bot = $this->createBot();
        $this->postText($this->ownerChatId, '/settings');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $text = $msg['text'];

        $this->assertStringContainsString(trans('bot.channel_sync_settings_title'), $text);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_dedup_line', ['dedup' => trans('bot.channel_sync_settings_dedup_channel')]), $text);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_mode_line', ['mode' => trans('bot.channel_sync_mode_immediate')]), $text);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_fast_line', ['fast' => trans('bot.channel_sync_settings_fast_on')]), $text);
        $this->assertStringContainsString(
            trans('bot.channel_sync_settings_attr_line', ['attr' => trans('bot.channel_sync_sub_attr_signed')]),
            $text
        );

        $rows = $msg['keyboard'];
        // مالک: ۳ منوی شخصی + پیش‌فرض ادمین‌ها + تنظیمات امضا (هوشمند) + نمایش منبع + بازگشت
        $this->assertCount(9, $rows);
        $labels = array_map(fn (array $row) => $row[0]['text'], $rows);
        $this->assertSame(
            [
                trans('bot.channel_sync_menu_dest'),
                trans('bot.channel_sync_menu_time'),
                trans('bot.channel_sync_menu_fast'),
                trans('bot.channel_sync_menu_defaults'),
                trans('bot.channel_poster_ssig_menu_entry'),
                trans('bot.channel_poster_pur_menu_entry'),
                trans('bot.channel_poster_hnd_menu_entry'),
                trans('bot.channel_sync_menu_attr'),
                trans('bot.channel_sync_sub_back'),
            ],
            $labels
        );
        foreach ($rows as $row) {
            $this->assertCount(1, $row); // هر دکمه در ردیف مستقل
        }
        $callbacks = array_map(fn (array $row) => $row[0]['callback_data'], $rows);
        $this->assertSame(
            [
                'cps:sub:dedup', 'cps:sub:time', 'cps:sub:fast', 'cps:sub:defaults', 'cp:ssig',
                'cp:pururl', 'cp:handle', 'cps:sub:attr', 'cps:back',
            ],
            $callbacks
        );
    }

    /**
     * UX-4b: منوی اصلی ادمین غیرمالک — بدون ردیف 👑.
     */
    public function test_ux_settings_main_menu_admin_has_no_defaults_row(): void
    {
        $bot = $this->createBot();
        $this->postText($this->ownerChatId, '/admin 222');
        $this->postText('222', '/settings');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];

        $this->assertSame('222', $msg['chat_id']);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_title'), $msg['text']);
        $labels = array_map(fn (array $row) => $row[0]['text'], $msg['keyboard']);
        $this->assertCount(5, $labels); // ۳ منوی شخصی + نمایش منبع + بازگشت
        $this->assertNotContains(trans('bot.channel_sync_menu_defaults'), $labels);
        $this->assertContains(trans('bot.channel_sync_menu_attr'), $labels);
    }

    /**
     * UX-5: زیرمنوها — کوتاه، ✅ کنار گزینه فعال، دکمه بازگشت درست.
     */
    public function test_ux_submenus_are_short_mark_active_and_have_correct_back(): void
    {
        $bot = $this->createBot();

        // زیرمنوی مقصدها (پیش‌فرض: فقط کانال مبدأ)
        $this->postCallback('cps:sub:dedup');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertStringContainsString(trans('bot.channel_sync_sub_dedup_title'), $msg['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_sub_dedup_channel') . ' ✅', $msg['text']);
        $this->assertStringNotContainsString(trans('bot.channel_sync_sub_dedup_platform') . ' ✅', $msg['text']);
        $this->assertSame(
            ['cps:dedup:channel', 'cps:dedup:platform', 'cps:menu'],
            array_map(fn (array $row) => $row[0]['callback_data'], $msg['keyboard'])
        );
        $this->assertCount(3, $msg['keyboard']);

        // زیرمنوی زمان انتشار (پیش‌فرض: فوری)
        $this->postCallback('cps:sub:time');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertStringContainsString(trans('bot.channel_sync_sub_time_title'), $msg['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_sub_time_immediate') . ' ✅', $msg['text']);
        $this->assertStringNotContainsString(trans('bot.channel_sync_sub_time_queue') . ' ✅', $msg['text']);
        $this->assertSame(
            ['cps:mode:immediate', 'cps:mode:queue', 'cps:menu'],
            array_map(fn (array $row) => $row[0]['callback_data'], $msg['keyboard'])
        );

        // زیرمنوی حالت سریع (پیش‌فرض: روشن)
        $this->postCallback('cps:sub:fast');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertStringContainsString(trans('bot.channel_sync_sub_fast_title'), $msg['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_sub_fast_on') . ' ✅', $msg['text']);
        $this->assertStringNotContainsString(trans('bot.channel_sync_sub_fast_off') . ' ✅', $msg['text']);
        $this->assertSame(
            ['cps:fast:on', 'cps:fast:off', 'cps:menu'],
            array_map(fn (array $row) => $row[0]['callback_data'], $msg['keyboard'])
        );

        // بازگشت به منوی اصلی
        $this->postCallback('cps:menu');
        $last = array_key_last($this->publisher->privateMessages);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_title'), $this->publisher->privateMessages[$last]['text']);
    }

    /**
     * UX-6: هر انتخاب مقدار در DB ذخیره می‌شود و پیام جدیدی ارسال نمی‌شود (فقط toast).
     */
    public function test_ux_each_selection_persists_without_new_message(): void
    {
        $bot = $this->createBot();

        $before = count($this->publisher->privateMessages);
        $this->postCallback('cps:dedup:platform');
        $this->assertCount($before, $this->publisher->privateMessages);
        $this->assertSame(ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM, ContentSyncAdminPrefs::getDedupMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postCallback('cps:mode:queue');
        $this->assertCount($before, $this->publisher->privateMessages);
        $this->assertSame(ContentSyncAdminPrefs::MODE_QUEUE, ContentSyncAdminPrefs::getMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postCallback('cps:fast:off');
        $this->assertCount($before, $this->publisher->privateMessages);
        $this->assertFalse(ContentSyncAdminPrefs::getFastMode($bot->id, $this->ownerChatId, 'bale'));

        // هر toast شامل برچسب انتخابی باشد
        $toasts = array_map(fn (array $c) => $c['text'], $this->publisher->callbacks);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_saved', ['label' => trans('bot.channel_sync_settings_dedup_platform')]), implode("\n", $toasts));
        $this->assertStringContainsString(trans('bot.channel_sync_settings_saved', ['label' => trans('bot.channel_sync_mode_queue')]), implode("\n", $toasts));
        $this->assertStringContainsString(trans('bot.channel_sync_settings_saved', ['label' => trans('bot.channel_sync_settings_fast_off')]), implode("\n", $toasts));

        // منوی اصلی حالا مقادیر تازه را نشان می‌دهد
        $this->postText($this->ownerChatId, '/settings');
        $last = array_key_last($this->publisher->privateMessages);
        $text = $this->publisher->privateMessages[$last]['text'];
        $this->assertStringContainsString(trans('bot.channel_sync_settings_dedup_line', ['dedup' => trans('bot.channel_sync_settings_dedup_platform')]), $text);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_mode_line', ['mode' => trans('bot.channel_sync_mode_queue')]), $text);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_fast_line', ['fast' => trans('bot.channel_sync_settings_fast_off')]), $text);
    }

    /**
     * UX-7: صفحه‌ی «👑 پیش‌فرض ادمین‌ها» فقط برای مالک.
     */
    public function test_ux_owner_defaults_page_is_owner_only(): void
    {
        $bot = $this->createBot();
        $this->postText($this->ownerChatId, '/admin 222');

        // ادمین: دکمه 👑 ندارد و اگر callback را بفرستد، رد می‌شود (بدون پیام)
        $this->postCallbackAs('222', 'cps:sub:defaults');
        $this->assertCount(1, $this->publisher->privateMessages); // فقط پیام /admin
        $lastCb = $this->publisher->callbacks[array_key_last($this->publisher->callbacks)];
        $this->assertSame(trans('bot.channel_poster_not_owner'), $lastCb['text']);

        // مالک: صفحه باز می‌شود
        $this->postCallback('cps:sub:defaults');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertSame($this->ownerChatId, $msg['chat_id']);
        $this->assertStringContainsString(trans('bot.channel_sync_sub_defaults_title'), $msg['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_sub_defaults_note'), $msg['text']);
        $this->assertSame(
            ['cps:sub:defaults:dedup', 'cps:sub:defaults:time', 'cps:sub:defaults:fast', 'cps:sub:defaults:attr', 'cps:menu'],
            array_map(fn (array $row) => $row[0]['callback_data'], $msg['keyboard'])
        );
    }

    /**
     * UX-7b: انتخاب پیش‌فرض مشترک فقط توسط مالک ذخیره می‌شود.
     */
    public function test_ux_owner_defaults_selection_persists_owner_only(): void
    {
        $bot = $this->createBot();
        $this->postText($this->ownerChatId, '/admin 222');

        $before = count($this->publisher->privateMessages);
        $this->postCallbackAs('222', 'cps:default:mode:queue');
        $this->assertCount($before, $this->publisher->privateMessages);
        $this->assertSame(ContentSyncAdminPrefs::MODE_IMMEDIATE, ContentSyncAdminPrefs::botDefaultsFor($bot->id, 'bale')->publish_mode);

        $this->postCallback('cps:default:mode:queue');
        $this->assertCount($before, $this->publisher->privateMessages);
        $this->assertSame(ContentSyncAdminPrefs::MODE_QUEUE, ContentSyncAdminPrefs::botDefaultsFor($bot->id, 'bale')->publish_mode);
        $this->assertSame($this->ownerChatId, (string) ContentSyncAdminPrefs::botDefaultsFor($bot->id, 'bale')->set_by_chat_id);

        // ادمینِ بدون تنظیم شخصی از پیش‌فرض مالک استفاده می‌کند
        $this->assertSame(ContentSyncAdminPrefs::MODE_QUEUE, ContentSyncAdminPrefs::getMode($bot->id, '222', 'bale'));
    }

    /**
     * UX-8: ده لینک پیاپی — هیچ‌کدام دکمه تنظیمات تکرار نمی‌کنند.
     */
    public function test_ux_ten_consecutive_links_show_keyboard_only_first(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        for ($i = 1; $i <= 10; $i++) {
            $this->postText($this->ownerChatId, "https://eitaa.com/alirezamehran_3/" . (1100 + $i));
            $last = array_key_last($this->publisher->privateMessages);
            $msg = $this->publisher->privateMessages[$last];
            if ($i === 1) {
                // §5: کیبورد فشرده فقط روی همان لینکِ اول
                $this->assertIsArray($msg['keyboard'] ?? null);
                $this->assertCount(2, $msg['keyboard']);
            } else {
                $this->assertNull($msg['keyboard'] ?? null, "لینک {$i} نباید کِی‌بورد داشته باشد");
            }
            $this->assertStringContainsString('/settings', $msg['text']);
            $this->assertStringNotContainsString(trans('bot.channel_sync_settings_title'), $msg['text']);
        }

        // تنظیمات همواره از /settings در دسترس است
        $this->postText($this->ownerChatId, '/settings');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertStringContainsString(trans('bot.channel_sync_settings_title'), $msg['text']);
        $this->assertIsArray($msg['keyboard']);
        $this->assertCount(9, $msg['keyboard']); // + تنظیمات امضا (هوشمند + pure url + handle) — فقط مالک
    }

    /**
     * UX-9: منوی تنظیمات روی مبدأ تلگرام — همان فرمت و callbackها.
     */
    public function test_ux_settings_works_on_telegram_origin(): void
    {
        $bot = $this->createBotWithTelegram();
        $this->createDestinations($bot->id);

        $this->postTextAsOrigin($this->ownerChatId, '/settings', 'telegram');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertSame($this->ownerChatId, $msg['chat_id']);
        $this->assertStringContainsString(trans('bot.channel_sync_settings_title'), $msg['text']);
        $this->assertCount(9, $msg['keyboard']); // + تنظیمات امضا (هوشمند + pure url + handle) — فقط مالک

        // ناوبری و انتخاب مقدار روی تلگرام
        $this->postCallbackAsOrigin($this->ownerChatId, 'cps:sub:time', 'telegram');
        $last = array_key_last($this->publisher->privateMessages);
        $this->assertStringContainsString(trans('bot.channel_sync_sub_time_title'), $this->publisher->privateMessages[$last]['text']);

        $before = count($this->publisher->privateMessages);
        $this->postCallbackAsOrigin($this->ownerChatId, 'cps:mode:queue', 'telegram');
        $this->assertCount($before, $this->publisher->privateMessages);
        $this->assertSame(
            ContentSyncAdminPrefs::MODE_QUEUE,
            ContentSyncAdminPrefs::getMode($bot->id, $this->ownerChatId, 'telegram')
        );
    }

    public function test_full_real_eitaa_link_simulation(): void
    {
        $bot = $this->createBot();
        $sourceDest = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_3',
            'channel_title' => 'Alireza',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
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

        // شبیه‌سازی لینک واقعی ایتا: لینک پیامی که در کانال مبدأ منتشر شده
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->assertStringContainsString(trans('bot.channel_sync_link_registered', ['id' => 1]), (string) $this->publisher->lastPrivateText());
        $this->postText($this->ownerChatId, 'متن واقعی مطلب از کانال ایتا');

        // به ایتای مبدأ دوباره‌ای ارسال نمی‌شود؛ فقط بله و تلگرام
        $this->assertCount(2, $this->publisher->publishes);
        $platforms = array_column($this->publisher->publishes, 'platform');
        sort($platforms);
        $this->assertSame(['bale', 'telegram'], $platforms);
        $this->assertNotContains('eitaa', $platforms);

        // تنها نسخه‌ی ایتا، نسخه‌ی خودِ مبدأ است (post_url و message_id اصل حفظ می‌شود)
        $source = ContentSyncVersion::where('content_id', 1)->where('platform', 'eitaa')->first();
        $this->assertNotNull($source);
        $this->assertSame(ContentSyncVersion::STATUS_EXISTING_SOURCE, $source->publish_status);
        $this->assertSame('https://eitaa.com/alirezamehran_3/1182', $source->post_url);
        $this->assertSame('1182', $source->message_id);
        $this->assertSame('confirmed', $source->link_status);
        $this->assertSame($sourceDest->id, $source->destination_id);

        // همه‌ی نسخه‌ها به یک محتوای مشترک تعلق دارند
        $versions = ContentSyncVersion::all();
        $this->assertCount(3, $versions);
        $this->assertTrue($versions->every(fn ($v) => (int) $v->content_id === 1));

        $report = (string) $this->publisher->lastPrivateText();
        $this->assertStringContainsString('در ایتا (alirezamehran_3) — ' . trans('bot.channel_sync_report_status_existing_source'), $report);
        $this->assertStringContainsString('در بله (stackoverflow_com) — ' . trans('bot.channel_sync_report_status_published'), $report);
        $this->assertStringContainsString('در تلگرام (Saber Blog) — ' . trans('bot.channel_sync_report_status_published'), $report);
        $this->assertStringContainsString(trans('bot.channel_sync_report_content_id', ['id' => 1]), $report);
        $this->assertStringEndsWith(trans('bot.channel_sync_help_footer'), $report);
    }

    public function test_fast_mode_off_asks_tag_again_when_ambiguous(): void
    {
        $bot = $this->createBot();
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '111111',
            'channel_title' => 'Bale One',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'telegram',
            'channel_chat_id' => '@sabernews',
            'channel_title' => 'Saber News',
            'tag' => 'tag2',
            'is_active' => true,
        ]);

        // خاموش کردن حالت سریع: آخرین تگ ادمین دیگر به‌کار نمی‌رود
        $this->postCallback('cps:fast:off');
        $this->assertFalse(ContentSyncAdminPrefs::getFastMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postText($this->ownerChatId, 'https://eitaa.com/otheruser/99');
        $this->postText($this->ownerChatId, 'متن با حالت سریع خاموش');

        // با دو تگ، دوباره سؤال می‌شود و هنوز منتشر نشده
        $this->assertStringContainsString(trans('bot.channel_sync_ask_tag'), (string) $this->publisher->lastPrivateText());
        $this->assertSame([], $this->publisher->publishes);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TAG, $this->ownerState($bot->id)->state);

        // انتخاب tag1 → فقط بله
        $this->postCallback('cps:tag:0');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(['bale'], array_column($this->publisher->publishes, 'platform'));
        $this->assertSame('published', ContentSyncVersion::where('content_id', 1)->where('platform', 'bale')->value('publish_status'));
    }

    /* ------------------------------------------------------------------
     * تست‌های پذیرش فاز ۴
     * ------------------------------------------------------------------ */

    /**
     * P4-§5/§4: زیرمنوی «نمایش منبع» از کیبورد اولین لینک باز می‌شود و
     * انتخاب فقط با toast ذخیره می‌شود.
     */
    public function test_p4_one_time_keyboard_attr_submenu_persists(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertIsArray($msg['keyboard'] ?? null);

        // باز کردن زیرمنوی نمایش منبع از کیبورد همان لینک
        $this->postCallback('cps:sub:attr');
        $last = array_key_last($this->publisher->privateMessages);
        $attr = $this->publisher->privateMessages[$last];
        $this->assertStringContainsString(trans('bot.channel_sync_sub_attr_title'), $attr['text']);
        $this->assertStringContainsString(trans('bot.channel_sync_sub_attr_signed') . ' ✅', $attr['text']);
        $this->assertSame(
            ['cps:attr:signed', 'cps:attr:link_only', 'cps:attr:none', 'cps:menu'],
            array_map(fn (array $row) => $row[0]['callback_data'], $attr['keyboard'])
        );

        // انتخاب → فقط toast، بدون پیام جدید
        $before = count($this->publisher->privateMessages);
        $this->postCallback('cps:attr:link_only');
        $this->assertCount($before, $this->publisher->privateMessages);
        $lastCb = $this->publisher->callbacks[array_key_last($this->publisher->callbacks)];
        $this->assertStringContainsString(
            trans('bot.channel_sync_settings_saved', ['label' => trans('bot.channel_sync_sub_attr_link_only')]),
            $lastCb['text']
        );
        $this->assertSame(
            ContentSyncAdminPrefs::ATTR_LINK_ONLY,
            ContentSyncAdminPrefs::getAttributionMode($bot->id, $this->ownerChatId, 'bale')
        );

        // لینک بعدی: کیبورد تکرار نمی‌شود
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1183');
        $last = array_key_last($this->publisher->privateMessages);
        $this->assertNull($this->publisher->privateMessages[$last]['keyboard'] ?? null);
    }

    /**
     * P4-§1: کانال مبدأ ناشناخته — یک‌بار سؤال می‌شود؛ کیبورد فقط مقصدهای
     * هم‌پلتفرم + «هیچکدام». تا نگاشت، هیچ انتشار مطمئنی نیست.
     */
    public function test_p4_unknown_source_asks_once_with_keyboard(): void
    {
        $bot = $this->createBot();
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezax_one',
            'channel_title' => 'Alireza One',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezax_two',
            'channel_title' => 'Alireza Two',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ContentSyncAdminPrefs::markSettingsAsked($bot->id, $this->ownerChatId, 'bale');

        $this->postText($this->ownerChatId, 'https://eitaa.com/unknown_channel/55');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];

        $this->assertSame(
            trans('bot.channel_sync_src_ask', [
                'id' => 1,
                'platform' => trans('bot.channel_poster_sig_eitaa'),
            ]),
            $msg['text']
        );
        $this->assertCount(3, $msg['keyboard']);
        $this->assertSame(
            [
                'alirezax_one — Alireza One (tag1)',
                'alirezax_two — Alireza Two (tag1)',
                trans('bot.channel_sync_src_other'),
            ],
            array_map(fn (array $row) => $row[0]['text'], $msg['keyboard'])
        );
        $this->assertSame(
            ['cps:src:0', 'cps:src:1', 'cps:src:none'],
            array_map(fn (array $row) => $row[0]['callback_data'], $msg['keyboard'])
        );
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_SOURCE, $this->ownerState($bot->id)->state);

        // تا نگاشت انجام نشود، هیچ انتشاری نیست
        $this->assertSame([], $this->publisher->publishes);
    }

    /**
     * P4-§1: انتخاب مقصد، نگاشت (username + لینک) را روی مقصد ذخیره می‌کند و
     * جریان به مرحله‌ی متن ادامه می‌یابد؛ کانال مبدأ خودکار حذف تکراری می‌شود.
     */
    public function test_p4_unknown_source_pick_saves_association_and_resolves(): void
    {
        $bot = $this->createBot();
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'channel_username' => 'stackoverflow_com',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        $tg = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'telegram',
            'channel_chat_id' => '-1001234567890',
            'channel_title' => 'Saber Blog',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ContentSyncAdminPrefs::markSettingsAsked($bot->id, $this->ownerChatId, 'bale');

        // لینک t.me/saberblog با مقصد عددی تلگرام نمی‌خواند → سؤال
        $this->postText($this->ownerChatId, 'https://t.me/saberblog/77');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertCount(2, $msg['keyboard']);
        $this->assertSame('-1001234567890 — Saber Blog (tag1)', $msg['keyboard'][0][0]['text']);

        // انتخاب مقصد → نگاشت ذخیره می‌شود (toast + پیام ثبت لینک)
        $before = count($this->publisher->privateMessages);
        $this->postCallback('cps:src:0');
        $lastCb = $this->publisher->callbacks[array_key_last($this->publisher->callbacks)];
        $this->assertSame(trans('bot.channel_sync_src_saved'), $lastCb['text']);
        $this->assertCount($before + 1, $this->publisher->privateMessages);
        $this->assertStringContainsString(
            trans('bot.channel_sync_link_registered', ['id' => 1]),
            $this->publisher->privateMessages[array_key_last($this->publisher->privateMessages)]['text']
        );

        $tg->refresh();
        $this->assertSame('saberblog', $tg->channel_username);
        $this->assertSame('https://t.me/saberblog/77', $tg->channel_link);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TEXT, $this->ownerState($bot->id)->state);

        // متن: فقط بله (کانال مبدأ تلگرام حالا دقیقاً شناسایی شده و حذف می‌شود)
        $this->postText($this->ownerChatId, 'متن بعد از شناسایی مبدأ');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(['bale'], array_column($this->publisher->publishes, 'platform'));
        $this->assertSame('tag1', ContentSyncContent::first()->tag);
    }

    /**
     * P4-§1: «هیچکدام» — هیچ مقصدی تغییر نمی‌کند و مبدأِ مبهم
     * (username در مقابل chat id عددی) اجازه‌ی انتشار نمی‌دهد.
     */
    public function test_p4_unknown_source_none_keeps_destinations_suspicious(): void
    {
        $bot = $this->createBot();
        $dest = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => '123456789',
            'channel_title' => 'Numeric Channel',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ContentSyncAdminPrefs::markSettingsAsked($bot->id, $this->ownerChatId, 'bale');

        $this->postText($this->ownerChatId, 'https://eitaa.com/someuser/9');
        $last = array_key_last($this->publisher->privateMessages);
        $this->assertStringContainsString(
            trans('bot.channel_sync_src_ask', ['id' => 1, 'platform' => trans('bot.channel_poster_sig_eitaa')]),
            $this->publisher->privateMessages[$last]['text']
        );

        $this->postCallback('cps:src:none');
        $dest->refresh();
        $this->assertNull($dest->channel_username);
        $this->assertNull($dest->channel_link);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TEXT, $this->ownerState($bot->id)->state);

        // متن: انتشاری نیست (مبدا مبهم)
        $this->postText($this->ownerChatId, 'متن مبدأ مبهم');
        $this->assertSame([], $this->publisher->publishes);
        $this->assertStringContainsString(trans('bot.channel_sync_no_targets'), (string) $this->publisher->lastPrivateText());
    }

    /**
     * P4-§1/§2: last_tag ادمین فقط وقتی مبدأ دقیقاً شناسایی شده به‌کار می‌رود؛
     * برای مبدأ ناشناخته سؤال تگ دوباره پرسیده می‌شود.
     */
    public function test_p4_last_tag_not_used_for_unknown_source(): void
    {
        $bot = $this->createBot();
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezax_one',
            'channel_title' => 'Alireza One',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'telegram',
            'channel_chat_id' => '@sabernews',
            'channel_title' => 'Saber News',
            'tag' => 'tag2',
            'is_active' => true,
        ]);
        ContentSyncAdminPrefs::markSettingsAsked($bot->id, $this->ownerChatId, 'bale');
        ContentSyncAdminPrefs::rememberTag($bot->id, $this->ownerChatId, 'bale', 'tag2');

        // مبدأ ناشناخته + last_tag=tag2 → میان‌بُری last_tag به‌کار نمی‌رود
        $this->postText($this->ownerChatId, 'https://eitaa.com/otheruser/99');
        $this->postCallback('cps:src:none');
        $this->postText($this->ownerChatId, 'متن مبدأ ناشناخته');

        $this->assertStringContainsString(trans('bot.channel_sync_ask_tag'), (string) $this->publisher->lastPrivateText());
        $this->assertSame([], $this->publisher->publishes);
        $this->assertSame(ChannelPosterBotController::STATE_SYNC_AWAITING_TAG, $this->ownerState($bot->id)->state);

        // انتخاب tag2 (ردیف دوم) → فقط کانال تگِ انتخابی
        $this->postCallback('cps:tag:1');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(['telegram'], array_column($this->publisher->publishes, 'platform'));
        $this->assertSame('@sabernews', $this->publisher->publishes[0]['channel_chat_id']);
        $this->assertSame('tag2', ContentSyncContent::first()->tag);
    }

    /**
     * P4-§2/§4: تگ و روش نمایش منبع در لحظه‌ی صف‌گذاری روی آیتم صف منجمد می‌شود.
     */
    public function test_p4_tag_and_attribution_frozen_on_queue(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postCallback('cps:mode:queue');
        $this->assertSame(ContentSyncAdminPrefs::MODE_QUEUE, ContentSyncAdminPrefs::getMode($bot->id, $this->ownerChatId, 'bale'));

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن با تگ و امضای منجمد');

        $content = ContentSyncContent::first();
        $this->assertSame('tag1', $content->tag);

        $queue = ChannelPosterQueue::first();
        $this->assertNotNull($queue);
        $this->assertSame('tag1', $queue->tag);
        $this->assertSame(ContentSyncAdminPrefs::ATTR_SIGNED, $queue->source_attribution);
    }

    /**
     * P4-§4: حالت پیش‌فرض (signed) — «📌 منبع: ...» + لینک مبدأ به انتهای
     * متن منتشرشده می‌چسبد؛ متن اصلی در دیتابیس دست‌نخورده می‌ماند.
     */
    public function test_p4_signed_attribution_appended_to_published_text(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1182');
        $this->postText($this->ownerChatId, 'متن اصلی بدون لینک');

        $this->assertCount(2, $this->publisher->publishes);
        $label = trans('bot.channel_sync_source_label', [
            'channel' => 'Alireza',
            'platform' => trans('bot.channel_poster_sig_eitaa'),
        ]);
        $suffix = $label . "\n" . 'https://eitaa.com/alirezamehran_3/1182';
        foreach ($this->publisher->publishes as $p) {
            $this->assertStringEndsWith($suffix, (string) $p['text']);
        }

        // متن اصلی در دیتابیس دست‌نخورده است (امضا فقط هنگام انتشار)
        $this->assertSame('متن اصلی بدون لینک', ContentSyncContent::first()->text);
    }

    /**
     * P4-§4: link_only → فقط لینک مبدأ؛ none → متن دقیقاً همان‌طور منتشر می‌شود.
     */
    public function test_p4_link_only_then_none_attribution(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        // link_only: فقط لینک مبدأ به انتهای متن
        $this->postCallback('cps:attr:link_only');
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1201');
        $this->postText($this->ownerChatId, 'متن دوم');
        $this->assertCount(2, $this->publisher->publishes);
        foreach ($this->publisher->publishes as $p) {
            $this->assertStringEndsWith('https://eitaa.com/alirezamehran_3/1201', (string) $p['text']);
        }

        // none: هیچ افزوده‌ای ندارد
        $this->postCallback('cps:attr:none');
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1202');
        $this->postText($this->ownerChatId, 'متن سوم');
        $rest = array_slice($this->publisher->publishes, 2);
        $this->assertCount(2, $rest);
        foreach ($rest as $p) {
            $this->assertSame('متن سوم', (string) $p['text']);
        }
    }

    /**
     * P4-§4: تغییر روش بعد از صف‌گذاری بی‌اثر است — امضا با مقدار
     * منجمد روی آیتم صف اعمال می‌شود.
     */
    public function test_p4_queue_item_keeps_frozen_attribution(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postCallback('cps:mode:queue');
        $this->postText($this->ownerChatId, 'https://eitaa.com/alirezamehran_3/1301');
        $this->postText($this->ownerChatId, 'متن صف با امضای منجمد');

        $queue = ChannelPosterQueue::first();
        $this->assertNotNull($queue);
        $this->assertSame(ContentSyncAdminPrefs::ATTR_SIGNED, $queue->source_attribution);

        // تغییر روش بعد از صف‌گذاری
        $this->postCallback('cps:attr:none');
        $this->assertSame(ContentSyncAdminPrefs::ATTR_NONE, ContentSyncAdminPrefs::getAttributionMode($bot->id, $this->ownerChatId, 'bale'));

        $service = $this->app->make(ChannelPosterQueuePublishService::class);
        $result = $service->publishItem($queue->fresh(), false);
        $this->assertSame('published', $result['status']);

        $this->assertCount(2, $this->publisher->publishes);
        $label = trans('bot.channel_sync_source_label', [
            'channel' => 'Alireza',
            'platform' => trans('bot.channel_poster_sig_eitaa'),
        ]);
        $suffix = $label . "\n" . 'https://eitaa.com/alirezamehran_3/1301';
        foreach ($this->publisher->publishes as $p) {
            $this->assertStringEndsWith($suffix, (string) $p['text']);
        }
    }

    /**
     * P4-§6: لینک واقعی بله که با مقصد ذخیره‌شده (chat id عددی) دقیقاً
     * می‌خواند: خودکار گره می‌خورد، username ذخیره می‌شود، کیبوردی نیست.
     */
    public function test_p4_known_source_auto_associates_without_keyboard(): void
    {
        $bot = $this->createBot();
        $bale = ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
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
        ContentSyncAdminPrefs::markSettingsAsked($bot->id, $this->ownerChatId, 'bale');

        $this->postText($this->ownerChatId, 'https://ble.ir/stackoverflow_com/8995754940207167599/42');
        $last = array_key_last($this->publisher->privateMessages);
        $msg = $this->publisher->privateMessages[$last];
        $this->assertStringContainsString(trans('bot.channel_sync_link_registered', ['id' => 1]), $msg['text']);
        $this->assertNull($msg['keyboard'] ?? null);

        // username و لینک روی مقصد ذخیره شده‌اند
        $bale->refresh();
        $this->assertSame('stackoverflow_com', $bale->channel_username);
        $this->assertSame('https://ble.ir/stackoverflow_com/8995754940207167599/42', $bale->channel_link);

        // نسخه‌ی مبدأ به مقصد گره خورده با لینک confirmed
        $source = ContentSyncVersion::where('platform', 'bale')
            ->where('publish_status', ContentSyncVersion::STATUS_EXISTING_SOURCE)
            ->first();
        $this->assertNotNull($source);
        $this->assertSame($bale->id, $source->destination_id);
        $this->assertSame('https://ble.ir/stackoverflow_com/8995754940207167599/42', $source->post_url);
        $this->assertSame(ContentSyncVersion::LINK_CONFIRMED, $source->link_status);

        // متن: فقط تلگرام (مبدأ بله حذف تکراری می‌شود)
        $this->postText($this->ownerChatId, 'متن از بله');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(['telegram'], array_column($this->publisher->publishes, 'platform'));
    }

    /* ------------------------------------------------------------------ */

    private function createBot(?string $token = null): Bot
    {
        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_token' => $token ?? $this->token,
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
            'channel_username' => 'stackoverflow_com',
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

    private function postText(string $chatId, string $text, ?string $token = null): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.($token ?? $this->token),
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
        $this->postCallbackAs($this->ownerChatId, $data);
    }

    private function postCallbackAs(string $chatId, string $data): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            [
                'update_id' => rand(1, 999999),
                'callback_query' => [
                    'id' => 'cb-' . $chatId . '-' . md5($data),
                    'from' => ['id' => (int) $chatId],
                    'data' => $data,
                    'message' => [
                        'chat' => ['id' => (int) $chatId, 'type' => 'private'],
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

    /**
     * رباتی که از مبدأ تلگرام (token و owner_chat_id تلگرام) پاسخ می‌دهد.
     */
    private function createBotWithTelegram(): Bot
    {
        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'telegram_bot_token' => '789:telegram-channel-poster-token',
            'telegram_bot_name' => 'channel_poster_bot',
            'telegram_bot_status' => 'Active',
            'telegram_owner_chat_id' => (int) $this->ownerChatId,
            'telegram_webhook_is_set' => true,
        ]);
    }

    private function postTextAsOrigin(string $chatId, string $text, string $origin, ?string $token = null): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin='.$origin.'&token='.($token ?? '789:telegram-channel-poster-token'),
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

    private function postCallbackAsOrigin(string $chatId, string $data, string $origin, ?string $token = null): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin='.$origin.'&token='.($token ?? '789:telegram-channel-poster-token'),
            [
                'update_id' => rand(1, 999999),
                'callback_query' => [
                    'id' => 'cb-' . $origin . '-' . $chatId . '-' . md5($data),
                    'from' => ['id' => (int) $chatId],
                    'data' => $data,
                    'message' => [
                        'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                    ],
                ],
            ]
        )->assertOk();
    }
}
