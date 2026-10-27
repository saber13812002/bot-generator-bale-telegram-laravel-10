<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelPosterBotController;
use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use Illuminate\Support\Carbon;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * CP-SMART-SIGNATURE-SETTINGS — §10 feature scenarios.
 *
 * Covered: SELF_ONLY, ALL_CHANNELS (single/multi line), EXCLUDE_SELF, CUSTOM
 * (1 pick / many picks / empty done / back nav), RANDOM (count 1 skips layout,
 * count 3 asks layout), queue random freeze + retry stability, per-destination
 * independence, ASK_EVERY_POST (short question, بله/خیر/دیگر نپرس/تنظیمات/الان/
 * invalid), DISABLED, multi-channel-per-platform custom pick, 2-tag wizard,
 * no-tag wizard, soroush text-only wizard with custom separator, rubika plain
 * rendering, token-leak guard.
 */
class ChannelPosterSmartSignatureTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const SOROUSH_TOKEN = '1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcd1234';

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

    // ------------------------------------------------------------------
    // 1. Wizard SELF_ONLY: no layout question, single-line markdown render
    // ------------------------------------------------------------------
    public function test_wizard_self_only_skips_layout_and_publishes_single_line_signature(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);

        $this->postCallback('cp:ssig');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_CHANNEL);
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_pick_channel'),
            $this->publisher->lastPrivateText()
        );

        $this->postCallback('cp:ssigc:' . $dest->id);
        $this->assertState(ChannelPosterBotController::STATE_SSIG_POLICY);

        $this->postCallback('cp:ssigpol:self_only');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_APPLY);
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_ask_apply'),
            $this->publisher->lastPrivateText()
        );

        $this->postCallback('cp:ssigap:always_apply');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->assertStringContainsString('«کانال ما»', $this->publisher->lastPrivateText());

        $this->postCallback('cp:ssigpv:yes');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_saved'),
            $this->publisher->lastPrivateText()
        );

        $dest->refresh();
        $this->assertTrue((bool) $dest->signature_enabled);
        $this->assertSame([
            'enabled' => true,
            'link_selection_policy' => 'self_only',
            'layout' => 'single_line',
            'separator' => ' | ',
            'selected_destinations' => [],
            'random_count' => 2,
            'application_policy' => 'always_apply',
        ], $dest->signature_settings);

        // ALWAYS_APPLY: no per-post question, straight to now/queue row.
        $this->postText('hello');
        $this->assertSame(trans('bot.channel_poster_ask_publish_options'), $this->publisher->lastPrivateText());
        $this->assertSame(
            ['cp:now', 'cp:queue'],
            $this->lastPrivateCallbackData()
        );

        $this->postCallback('cp:now');
        $this->assertCount(1, $this->publisher->publishes);
        $pub = $this->publisher->publishes[0];
        $this->assertSame('bale', $pub['platform']);
        $this->assertSame('hello' . "\n\n[کانال ما](https://t.me/ch)", $pub['text']);
        $this->assertNull($pub['parse_mode']);
        $this->assertNull($pub['reply_markup']);
        $this->assertNoState();
        $this->assertNoTokenLeak();
    }

    // ------------------------------------------------------------------
    // 2. Wizard ALL_CHANNELS + multi_line: html anchors (telegram) /
    //    markdown links (bale), insertion order
    // ------------------------------------------------------------------
    public function test_wizard_all_channels_multi_line_renders_per_platform(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            'signature_label' => 'کانال ما',
        ]);
        $telegram = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
        ]);

        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $bale->id);
        $this->postCallback('cp:ssigpol:all_channels');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_LAYOUT);

        $this->postCallback('cp:ssiglay:multi_line');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_APPLY);
        $this->postCallback('cp:ssigap:always_apply');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->assertStringContainsString(
            'کانال ما' . "\n" . 'https://t.me/ch',
            $this->publisher->lastPrivateText()
        );

        $this->postCallback('cp:ssigpv:yes');
        $this->assertNoState();

        // The wizard saved the BALE destination; configure the telegram
        // destination identically so both signatures are smart multi-line.
        $svc = $this->app->make(ChannelPosterBotService::class);
        $svc->saveSmartSignatureSettings($telegram->id, $this->smartSettings([
            'link_selection_policy' => 'all_channels',
            'layout' => 'multi_line',
        ]));

        $this->postText('hello');
        $this->postCallback('cp:now');

        // Platform-alphabetical order (sqlite index scan): bale first, telegram second.
        $balePub = $this->publisher->publishes[0];
        $tgPub = $this->publisher->publishes[1];

        // Bale (plain Markdown): multi_line = one link per line; the label is
        // already part of the [label](url) link, so no separate label line.
        $this->assertSame(
            'hello' . "\n\n[کانال ما](https://ble.ir/ch)\n[در تلگرام](https://t.me/ch)",
            $balePub['text']
        );
        $this->assertNull($balePub['parse_mode']);

        $this->assertSame(
            'hello' . "\n\nکانال ما:\n<a href=\"https://ble.ir/ch\">کانال ما</a>\nدر تلگرام:\n<a href=\"https://t.me/ch\">در تلگرام</a>",
            $tgPub['text']
        );
        $this->assertSame('html', $tgPub['parse_mode']);
    }

    // ------------------------------------------------------------------
    // 3. Soroush origin: full wizard via TEXT input (native keyboards
    //    stripped), custom separator, then text-freeze publish
    // ------------------------------------------------------------------
    public function test_wizard_via_soroush_text_with_custom_separator(): void
    {
        $bot = $this->createBaleBot();
        $bot->update([
            'soroush_bot_token' => self::SOROUSH_TOKEN,
            'soroush_bot_name' => 'channel_poster_soroush_bot',
            'soroush_bot_status' => 'Active',
            'soroush_owner_chat_id' => (int) $this->ownerChatId,
            'soroush_webhook_is_set' => true,
        ]);

        $soroush = $this->createDestination($bot->id, 'soroush', 'tag1', [
            'channel_link' => 'https://soroush.me/ch',
        ]);
        $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
        ]);

        // Enter wizard via callback (menu), then every step via TEXT.
        $this->postSoroushCallback('cp:ssig');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_CHANNEL);

        $this->postSoroushText('2'); // channel: soroush (platform order: bale=1, soroush=2)
        $this->assertState(ChannelPosterBotController::STATE_SSIG_POLICY);

        $this->postSoroushText('2'); // policy: all_channels
        $this->assertState(ChannelPosterBotController::STATE_SSIG_LAYOUT);
        $this->assertStringContainsString(
            trans('bot.channel_poster_platform_native_hint'),
            $this->publisher->lastPrivateText()
        );

        $this->postSoroushText('1'); // layout: single_line
        $this->assertState(ChannelPosterBotController::STATE_SSIG_SEPARATOR);

        $this->postSoroushText(' – '); // trimmed to '–'
        $this->assertState(ChannelPosterBotController::STATE_SSIG_APPLY);

        $this->postSoroushText('1'); // apply: always
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->assertStringContainsString(
            '«https://ble.ir/ch–https://soroush.me/ch»',
            $this->publisher->lastPrivateText()
        );

        $this->postSoroushText('بله');
        $this->assertNoState();
        $soroush->refresh();
        $this->assertSame('–', $soroush->signature_settings['separator']);
        $this->assertSame('all_channels', $soroush->signature_settings['link_selection_policy']);

        // Publish flow: ALWAYS_APPLY → straight to now/queue row.
        $this->postSoroushText('x');
        $this->assertStringContainsString(
            trans('bot.channel_poster_ask_publish_options'),
            $this->publisher->lastPrivateText()
        );

        $this->postSoroushCallback('cp:now');
        $this->assertCount(2, $this->publisher->publishes);

        $balePub = $this->publisher->publishes[0];
        $this->assertSame('bale', $balePub['platform']);
        $this->assertSame(
            'x' . "\n\nدر بله:\n[https://ble.ir/ch](https://ble.ir/ch)",
            $balePub['text']
        );
        $this->assertNull($balePub['parse_mode']);

        $soroushPub = $this->publisher->publishes[1];
        $this->assertSame('soroush', $soroushPub['platform']);
        $this->assertSame(
            'x' . "\n\nدر بله: https://ble.ir/ch–در سروش: https://soroush.me/ch",
            $soroushPub['text']
        );
        $this->assertNull($soroushPub['parse_mode']);
    }

    // ------------------------------------------------------------------
    // 4. EXCLUDE_SELF: no partial signature — destination whose only
    //    "other" channel has no public link publishes plain
    // ------------------------------------------------------------------
    public function test_exclude_self_publishes_other_link_and_empty_for_self(): void
    {
        $bot = $this->createBot();
        $soroush = $this->createDestination($bot->id, 'soroush', 'tag1'); // NO link
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
        ]);
        $svc = $this->app->make(ChannelPosterBotService::class);
        $svc->saveSmartSignatureSettings($soroush->id, $this->smartSettings(['link_selection_policy' => 'exclude_self']));
        $svc->saveSmartSignatureSettings($bale->id, $this->smartSettings(['link_selection_policy' => 'exclude_self']));

        $this->postText('t');
        $this->postCallback('cp:now');

        $balePub = $this->publisher->publishes[0];
        $this->assertSame('t', $balePub['text']);

        $soroushPub = $this->publisher->publishes[1];
        $this->assertSame('t' . "\n\nدر بله: https://ble.ir/ch", $soroushPub['text']);
        $this->assertNull($soroushPub['parse_mode']);
    }

    // ------------------------------------------------------------------
    // 5. CUSTOM: two picks, separator back-navigation, saved order
    // ------------------------------------------------------------------
    public function test_wizard_custom_picks_with_back_navigation(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            'signature_label' => 'کانال ما',
        ]);
        $telegram = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/tg',
            'signature_label' => 'TG',
        ]);
        $soroush = $this->createDestination($bot->id, 'soroush', 'tag1', [
            'channel_link' => 'https://soroush.me/sr',
            'signature_label' => 'SR',
        ]);

        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $bale->id);
        $this->postCallback('cp:ssigpol:custom');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_CUSTOM_PICK);

        $this->postCallback('cp:ssigck:' . $telegram->id);
        $this->postCallback('cp:ssigck:' . $soroush->id);
        $this->postCallback('cp:ssigck:done');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_LAYOUT); // 2 picks → layout

        $this->postCallback('cp:ssiglay:single_line');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_SEPARATOR);

        $this->postText('بازگشت'); // SEPARATOR → back to LAYOUT
        $this->assertState(ChannelPosterBotController::STATE_SSIG_LAYOUT);

        $this->postCallback('cp:ssiglay:multi_line');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_APPLY);
        $this->postCallback('cp:ssigap:always_apply');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->assertStringContainsString('«SR' . "\n" . 'TG»', $this->publisher->lastPrivateText());

        $this->postCallback('cp:ssigpv:yes');
        $this->assertNoState();

        $bale->refresh();
        $this->assertSame([$telegram->id, $soroush->id], $bale->signature_settings['selected_destinations']);
        $this->assertSame('multi_line', $bale->signature_settings['layout']);
    }

    // ------------------------------------------------------------------
    // 6. CUSTOM: «انجام شد» with zero picks is rejected
    // ------------------------------------------------------------------
    public function test_wizard_custom_empty_picks_rejected(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', ['channel_link' => 'https://ble.ir/ch']);
        $this->createDestination($bot->id, 'telegram', 'tag1', ['channel_link' => 'https://t.me/tg']);

        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $bale->id);
        $this->postCallback('cp:ssigpol:custom');
        $this->postCallback('cp:ssigck:done');

        $this->assertState(ChannelPosterBotController::STATE_SSIG_CUSTOM_PICK);
        $msgs = $this->publisher->privateMessages;
        // «انجام شد» با صفر انتخاب → پیام خطا + نمایش دوبارهٔ سؤال انتخاب
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_custom_empty'),
            $msgs[count($msgs) - 2]['text']
        );
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_ask_custom'),
            $this->publisher->lastPrivateText()
        );
        $bale->refresh();
        $this->assertNull($bale->signature_settings);
    }

    // ------------------------------------------------------------------
    // 7/8. RANDOM: count=1 skips layout, count=3 asks layout
    // ------------------------------------------------------------------
    public function test_wizard_random_count_one_skips_layout(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);

        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $bale->id);
        $this->postCallback('cp:ssigpol:random');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_RANDOM_COUNT);

        $this->postCallback('cp:ssigrc:1');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_APPLY); // NO layout step

        $this->postCallback('cp:ssigap:always_apply');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->assertStringContainsString('«کانال ما»', $this->publisher->lastPrivateText());

        $this->postCallback('cp:ssigpv:yes');
        $bale->refresh();
        $this->assertSame(1, $bale->signature_settings['random_count']);
        $this->assertSame('random', $bale->signature_settings['link_selection_policy']);
    }

    public function test_wizard_random_count_three_asks_layout(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', ['channel_link' => 'https://t.me/ch']);

        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $bale->id);
        $this->postCallback('cp:ssigpol:random');
        $this->postCallback('cp:ssigrc:3');

        $this->assertState(ChannelPosterBotController::STATE_SSIG_LAYOUT);
    }

    // ------------------------------------------------------------------
    // 9. Queue: random selection frozen at enqueue, stable across retry
    // ------------------------------------------------------------------
    public function test_queue_random_selection_frozen_and_stable_on_retry(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            'signature_label' => 'کانال بله',
        ]);
        $telegram = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال تلگرام',
        ]);
        $soroush = $this->createDestination($bot->id, 'soroush', 'tag1', [
            'channel_link' => 'https://soroush.me/ch',
            'signature_label' => 'کانال سروش',
        ]);
        $svc = $this->app->make(ChannelPosterBotService::class);
        foreach ([$bale, $telegram, $soroush] as $d) {
            $svc->saveSmartSignatureSettings($d->id, $this->smartSettings([
                'link_selection_policy' => 'random',
                'random_count' => 2,
            ]));
        }
        // resolveByTag بدون orderBy → در sqlite ترتیب پلتفرمی (bale, soroush, telegram)
        $order = [$bale, $soroush, $telegram];

        $this->postText('hello');
        $this->postCallback('cp:queue');
        $this->assertStringContainsString('در صف قرار گرفت', $this->publisher->lastPrivateText());

        /** @var ChannelPosterQueue $item */
        $item = ChannelPosterQueue::first();
        $this->assertNotNull($item);
        $this->assertTrue((bool) $item->signature_enabled);
        $frozen = $item->signature_selection;
        $this->assertIsArray($frozen);
        $this->assertCount(3, $frozen);
        foreach ($frozen as $ids) {
            $this->assertCount(2, $ids);
        }

        // Run 1: bale + telegram fail, soroush succeeds.
        $this->publisher->failPlatforms = ['bale', 'telegram'];
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id, '--all' => true])->assertFailed();
        $this->assertCount(3, $this->publisher->publishes);
        $this->assertSame('soroush', $this->publisher->publishes[1]['platform']);
        $this->assertSame(
            $this->expectedFrozenText('hello', 'soroush', $soroush, $frozen, $order),
            $this->publisher->publishes[1]['text']
        );
        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->fresh()->status);

        // Run 2: everything succeeds; soroush skipped via success log,
        // bale/telegram reuse the FROZEN selection (retry-stable).
        // refresh() first: the in-memory model is stale (run 1 set the DB row
        // to FAILED), so without it the update below would be a no-op.
        $item->refresh();
        $item->update(['status' => ChannelPosterQueue::STATUS_PENDING]);
        $this->publisher->failPlatforms = [];
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id, '--all' => true])->assertSuccessful();

        $this->assertCount(5, $this->publisher->publishes);
        $soroushPublishes = array_filter($this->publisher->publishes, fn ($p) => $p['platform'] === 'soroush');
        $this->assertCount(1, $soroushPublishes);

        $baleRun2 = null;
        $tgRun2 = null;
        foreach ($this->publisher->publishes as $p) {
            if ($p['platform'] === 'bale') {
                $baleRun2 = $p['text'];
            }
            if ($p['platform'] === 'telegram') {
                $tgRun2 = $p['text'];
            }
        }
        $this->assertSame($this->expectedFrozenText('hello', 'bale', $bale, $frozen, $order), $baleRun2);
        $this->assertSame($this->expectedFrozenText('hello', 'telegram', $telegram, $frozen, $order), $tgRun2);
    }

    // ------------------------------------------------------------------
    // 10. Independence: changing one destination never alters another
    // ------------------------------------------------------------------
    public function test_changing_one_destination_never_alters_another(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            'signature_label' => 'کانال ما',
        ]); // legacy (signature_enabled NULL)
        $telegram = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'TG',
        ]);
        $svc = $this->app->make(ChannelPosterBotService::class);
        $svc->saveSmartSignatureSettings($telegram->id, $this->smartSettings());

        $this->postText('a');
        $this->postCallback('cp:now');
        $baleText1 = $this->publisher->publishes[0]['text'];
        $tgText1 = $this->publisher->publishes[1]['text'];
        $this->assertSame('a' . "\n\n<a href=\"https://t.me/ch\">TG</a>", $tgText1);

        // Now reconfigure the TELEGRAM destination to all_channels/multi.
        $svc->saveSmartSignatureSettings($telegram->id, $this->smartSettings([
            'link_selection_policy' => 'all_channels',
            'layout' => 'multi_line',
        ]));

        $this->postText('b');
        $this->postCallback('cp:now');
        $this->assertCount(4, $this->publisher->publishes);

        // Bale (legacy) signature is byte-identical (only the content letter changes).
        $this->assertSame('b' . substr($baleText1, 1), $this->publisher->publishes[2]['text']);

        // Telegram now shows BOTH channels.
        $this->assertStringContainsString('<a href="https://ble.ir/ch">کانال ما</a>', $this->publisher->publishes[3]['text']);
        $this->assertStringContainsString('<a href="https://t.me/ch">TG</a>', $this->publisher->publishes[3]['text']);
    }

    // ------------------------------------------------------------------
    // 11. ASK_EVERY_POST: only the short question, then بله via text
    // ------------------------------------------------------------------
    public function test_ask_every_post_shows_only_short_question_then_yes(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);
        $this->configureSmart($dest, ['application_policy' => 'ask_every_post']);

        $this->postText('hello');
        $this->assertSame(trans('bot.channel_poster_ssig_ask_this_post'), $this->publisher->lastPrivateText());
        $rows = $this->lastPrivateCallbackRows();
        $this->assertCount(2, $rows);
        $this->assertSame(['cp:ssigp:yes', 'cp:ssigp:no'], $rows[0]);
        $this->assertSame(['cp:ssigp:settings', 'cp:ssigp:stop'], $rows[1]);
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_DESTINATION);

        $this->postText('بله');
        $this->assertSame(['cp:now', 'cp:queue'], $this->lastPrivateCallbackData());

        $this->postCallback('cp:now');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('hello' . "\n\n[کانال ما](https://t.me/ch)", $this->publisher->publishes[0]['text']);
        $this->assertNull($this->publisher->publishes[0]['parse_mode']);
    }

    // ------------------------------------------------------------------
    // 12. ASK: خیر is temporary — permanent settings preserved
    // ------------------------------------------------------------------
    public function test_ask_no_is_temporary_and_preserves_permanent_settings(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);
        $this->configureSmart($dest, ['application_policy' => 'ask_every_post']);

        $this->postText('hello');
        $this->postText('خیر');
        $this->postCallback('cp:now');

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('hello', $this->publisher->publishes[0]['text']);

        $dest->refresh();
        $this->assertTrue((bool) $dest->signature_enabled);
        $this->assertSame('ask_every_post', $dest->signature_settings['application_policy']);

        // Next post asks again (temporary choice was not persisted).
        $this->postText('again');
        $this->assertSame(trans('bot.channel_poster_ssig_ask_this_post'), $this->publisher->lastPrivateText());
    }

    // ------------------------------------------------------------------
    // 13. ASK: دیگر نپرس permanently switches to always_apply
    // ------------------------------------------------------------------
    public function test_ask_stop_asking_permanently_switches_to_always_apply(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);
        $this->configureSmart($dest, ['application_policy' => 'ask_every_post']);

        $this->postText('hello');
        $this->postText('دیگر نپرس');
        $this->assertSame(trans('bot.channel_poster_ssig_stop_saved'), $this->publisher->lastPrivateText());

        $dest->refresh();
        $this->assertSame('always_apply', $dest->signature_settings['application_policy']);

        // Next post: NO question.
        $this->postText('world');
        $this->assertStringNotContainsString(
            trans('bot.channel_poster_ssig_ask_this_post'),
            $this->publisher->lastPrivateText()
        );
        $this->assertSame(['cp:now', 'cp:queue'], $this->lastPrivateCallbackData());
    }

    // ------------------------------------------------------------------
    // 14. ASK: تنظیمات opens the wizard with the pending content intact
    // ------------------------------------------------------------------
    public function test_ask_settings_opens_wizard_and_restores_pending(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);
        $this->configureSmart($dest, ['application_policy' => 'ask_every_post']);

        $this->postText('hello');
        $this->postText('تنظیمات');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_CHANNEL);

        $this->postCallback('cp:ssigc:' . $dest->id);
        $this->postCallback('cp:ssigpol:self_only');
        $this->postCallback('cp:ssigap:always_apply');
        $this->postCallback('cp:ssigpv:yes');

        // Wizard finished WITH the pending content → straight to now/queue,
        // no signature question (saved policy is always_apply).
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_DESTINATION);
        $this->assertSame(['cp:now', 'cp:queue'], $this->lastPrivateCallbackData());

        $this->postCallback('cp:now');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('hello' . "\n\n[کانال ما](https://t.me/ch)", $this->publisher->publishes[0]['text']);
    }

    // ------------------------------------------------------------------
    // 15. ASK: «الان» = publish immediately without signature
    // ------------------------------------------------------------------
    public function test_ask_now_text_publishes_plain_immediately(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);
        $this->configureSmart($dest, ['application_policy' => 'ask_every_post']);

        $this->postText('hello');
        $this->postText('الان');

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('hello', $this->publisher->publishes[0]['text']);
        $this->assertNoState();
    }

    // ------------------------------------------------------------------
    // 16. ASK: invalid text re-asks with hint
    // ------------------------------------------------------------------
    public function test_ask_invalid_text_asks_again_with_hint(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);
        $this->configureSmart($dest, ['application_policy' => 'ask_every_post']);

        $this->postText('hello');
        $this->postText('foo bar');

        $this->assertSame(
            trans('bot.channel_poster_ssig_ask_this_post') . "\n" . trans('bot.channel_poster_ssig_text_hint_yes'),
            $this->publisher->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_DESTINATION);
    }

    // ------------------------------------------------------------------
    // 17. DISABLED: no question, plain publish
    // ------------------------------------------------------------------
    public function test_disabled_policy_never_asks_and_publishes_plain(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
        ]);
        $this->configureSmart($dest, [
            'enabled' => false,
            'application_policy' => 'disabled',
        ]);

        $this->postText('hello');
        $this->assertSame(trans('bot.channel_poster_ask_publish_options'), $this->publisher->lastPrivateText());
        $this->assertSame(['cp:now', 'cp:queue'], $this->lastPrivateCallbackData());

        $this->postCallback('cp:now');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('hello', $this->publisher->publishes[0]['text']);
    }

    // ------------------------------------------------------------------
    // 18. Wizard cancel / invalid inputs / back / preview-no
    // ------------------------------------------------------------------
    public function test_wizard_cancel_invalid_and_back_behaviors(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', ['channel_link' => 'https://ble.ir/ch']);

        // (a) cancel at channel step → nothing saved
        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $dest->id);
        $this->postCallback('cp:ssigcancel');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_cancelled'),
            $this->publisher->lastPrivateText()
        );
        $dest->refresh();
        $this->assertNull($dest->signature_settings);

        // (b) invalid number at POLICY keeps state
        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $dest->id);
        $this->postText('9');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_POLICY);
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_invalid_number'),
            $this->publisher->lastPrivateText()
        );

        // (c) invalid count at RANDOM_COUNT
        $this->postCallback('cp:ssigpol:random');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_RANDOM_COUNT);
        $this->postText('99');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_RANDOM_COUNT);
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_invalid_count'),
            $this->publisher->lastPrivateText()
        );

        // (d) invalid preview answer keeps state
        $this->postCallback('cp:ssigbk'); // RANDOM_COUNT → back to POLICY
        $this->assertState(ChannelPosterBotController::STATE_SSIG_POLICY);
        $this->postCallback('cp:ssigpol:self_only');
        $this->postCallback('cp:ssigap:always_apply');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->postText('foo');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_confirm_invalid'),
            $this->publisher->lastPrivateText()
        );

        // (e) preview «خیر» → cancelled, DB untouched
        $this->postText('خیر');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_cancelled'),
            $this->publisher->lastPrivateText()
        );
        $dest->refresh();
        $this->assertNull($dest->signature_settings);
    }

    // ------------------------------------------------------------------
    // 19. Rubika: multi-line signature stays plain (no href/markdown)
    // ------------------------------------------------------------------
    public function test_rubika_multi_signature_renders_plain_urls(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            'signature_label' => 'کانال بله',
        ]);
        $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال تلگرام',
        ]);
        $rubika = $this->createDestination($bot->id, 'rubika', 'tag1', [
            'channel_link' => 'https://rubika.ir/ch',
            'signature_label' => 'کانال روبیکا',
        ]);
        $svc = $this->app->make(ChannelPosterBotService::class);
        foreach (ChannelPosterDestination::where('bot_id', $bot->id)->get() as $d) {
            $svc->saveSmartSignatureSettings($d->id, $this->smartSettings([
                'link_selection_policy' => 'all_channels',
            ]));
        }

        $this->postText('t');
        $this->postCallback('cp:now');

        $rubikaPub = null;
        $tgPub = null;
        foreach ($this->publisher->publishes as $p) {
            if ($p['platform'] === 'rubika') {
                $rubikaPub = $p;
            }
            if ($p['platform'] === 'telegram') {
                $tgPub = $p;
            }
        }
        $this->assertNotNull($rubikaPub);
        $this->assertNull($rubikaPub['parse_mode']);
        $this->assertStringNotContainsString('href', $rubikaPub['text']);
        $this->assertSame(
            't' . "\n\nکانال بله: https://ble.ir/ch | کانال روبیکا: https://rubika.ir/ch | کانال تلگرام: https://t.me/ch",
            $rubikaPub['text']
        );

        $this->assertSame('html', $tgPub['parse_mode']);
        $this->assertStringContainsString('<a href="https://ble.ir/ch">کانال بله</a>', $tgPub['text']);
        $this->assertStringContainsString('<a href="https://t.me/ch">کانال تلگرام</a>', $tgPub['text']);
        $this->assertStringContainsString('<a href="https://rubika.ir/ch">کانال روبیکا</a>', $tgPub['text']);
    }

    // ------------------------------------------------------------------
    // 20. Multi-channel-per-platform: custom pick of the OTHER telegram
    // ------------------------------------------------------------------
    public function test_custom_pick_can_target_other_channel_same_platform(): void
    {
        $bot = $this->createBot();
        $tg1 = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/t1',
        ]);
        $tg2 = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_chat_id' => 'telegram-channel-2',
            'channel_link' => 'https://t.me/t2',
        ]);

        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $tg1->id);
        $this->postCallback('cp:ssigpol:custom');
        $this->postCallback('cp:ssigck:' . $tg2->id);
        $this->postCallback('cp:ssigck:done'); // 1 pick → NO layout step
        $this->assertState(ChannelPosterBotController::STATE_SSIG_APPLY);
        $this->postCallback('cp:ssigap:always_apply');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_PREVIEW);
        $this->assertStringContainsString('«https://t.me/t2»', $this->publisher->lastPrivateText());
        $this->postCallback('cp:ssigpv:yes');
        $this->assertNoState();

        $tg1->refresh();
        $this->assertSame([$tg2->id], $tg1->signature_settings['selected_destinations']);

        $this->postText('x');
        $this->postCallback('cp:now');

        $tg1Pub = null;
        foreach ($this->publisher->publishes as $p) {
            if ($p['channel_chat_id'] === 'telegram-channel-1') {
                $tg1Pub = $p;
            }
        }
        $this->assertNotNull($tg1Pub);
        $this->assertSame('x' . "\n\n<a href=\"https://t.me/t2\">در تلگرام</a>", $tg1Pub['text']);
        $this->assertSame('html', $tg1Pub['parse_mode']);
    }

    // ------------------------------------------------------------------
    // 21. Two tags → tag-picker step first
    // ------------------------------------------------------------------
    public function test_wizard_shows_tag_step_when_multiple_tags(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'bale', 'tag1', ['channel_link' => 'https://ble.ir/ch']);
        $this->createDestination($bot->id, 'telegram', 'tag2', ['channel_link' => 'https://t.me/ch']);

        $this->postCallback('cp:ssig');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_TAG);
        $this->assertStringContainsString(
            trans('bot.channel_poster_ssig_ask_tag'),
            $this->publisher->lastPrivateText()
        );

        $this->postCallback('cp:ssigt:0');
        $this->assertState(ChannelPosterBotController::STATE_SSIG_CHANNEL);
    }

    // ------------------------------------------------------------------
    // 22. No tags → friendly message, no state
    // ------------------------------------------------------------------
    public function test_wizard_without_any_tag_shows_message_and_no_state(): void
    {
        $this->createBot();
        $this->postCallback('cp:ssig');
        $this->assertSame(trans('bot.channel_poster_ssig_no_tag'), $this->publisher->lastPrivateText());
        $this->assertNoState();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build the expected smart multi-line signature text for a destination
     * using a frozen random selection (single_line layout, default separator).
     */
    private function expectedFrozenText(
        string $content,
        string $platform,
        ChannelPosterDestination $dest,
        array $frozen,
        array $order
    ): string {
        $ids = array_map('intval', (array) ($frozen[$dest->id] ?? []));
        $selected = array_values(array_filter(
            $order,
            fn (ChannelPosterDestination $d) => in_array($d->id, $ids, true)
        ));
        $lines = array_map(
            function (ChannelPosterDestination $d) use ($platform) {
                $label = $d->signature_label ?? $d->channel_link;
                return match ($platform) {
                    'telegram', 'eitaa' => "<a href=\"{$d->channel_link}\">{$label}</a>",
                    'bale' => "[{$label}]({$d->channel_link})",
                    default => $label . ': ' . $d->channel_link,
                };
            },
            $selected
        );

        return $content . "\n\n" . implode(' | ', $lines);
    }

    /** Default smart-signature draft with overrides (for direct config). */
    private function smartSettings(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'link_selection_policy' => 'self_only',
            'layout' => 'single_line',
            'separator' => ' | ',
            'selected_destinations' => [],
            'random_count' => 2,
            'application_policy' => 'always_apply',
        ], $overrides);
    }

    private function configureSmart(ChannelPosterDestination $dest, array $overrides = []): ChannelPosterDestination
    {
        return $this->app->make(ChannelPosterBotService::class)->saveSmartSignatureSettings(
            $dest->id,
            $this->smartSettings($overrides)
        );
    }

    private function postText(string $text): void
    {
        $this->postJson('/api/webhook-channel-poster?origin=bale&token=' . $this->token, [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => (int) $this->ownerChatId, 'is_bot' => false],
                'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                'date' => time(),
                'text' => $text,
            ],
        ])->assertOk();
    }

    private function postCallback(string $data): void
    {
        $this->postJson('/api/webhook-channel-poster?origin=bale&token=' . $this->token, [
            'update_id' => 2,
            'callback_query' => [
                'id' => 'cb-' . md5($data),
                'from' => ['id' => (int) $this->ownerChatId],
                'data' => $data,
                'message' => [
                    'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                ],
            ],
        ])->assertOk();
    }

    private function postSoroushText(string $text): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN,
            [
                'update_id' => 1,
                'message' => [
                    'message_id' => 10,
                    'from' => ['id' => (int) $this->ownerChatId, 'is_bot' => false],
                    'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                    'date' => time(),
                    'text' => $text,
                ],
            ]
        )->assertOk();
    }

    private function postSoroushCallback(string $data): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN,
            [
                'update_id' => 2,
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

    private function assertState(string $state): void
    {
        $bot = Bot::first();
        $user = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
        $this->assertSame(
            $state,
            BotUserState::where('bot_user_id', $user->id)->value('state')
        );
    }

    private function assertNoState(): void
    {
        $bot = Bot::first();
        $user = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
        if (!$user) {
            // No bot-user row was ever created (only callbacks were sent) —
            // in that case there must be no state rows at all.
            $this->assertSame(0, BotUserState::count());

            return;
        }
        $this->assertNull(BotUserState::where('bot_user_id', $user->id)->value('state'));
    }

    /** Flattened callback_data of the last private-message keyboard. */
    private function lastPrivateCallbackData(): array
    {
        return array_merge([], ...array_map(
            fn (array $row) => array_column($row, 'callback_data'),
            $this->lastPrivateKeyboard()
        ));
    }

    /** Per-row callback_data of the last private-message keyboard. */
    private function lastPrivateCallbackRows(): array
    {
        return array_map(
            fn (array $row) => array_column($row, 'callback_data'),
            $this->lastPrivateKeyboard()
        );
    }

    private function lastPrivateKeyboard(): array
    {
        $msgs = $this->publisher->privateMessages;

        return $msgs ? ($msgs[count($msgs) - 1]['keyboard'] ?? []) : [];
    }

    private function assertNoTokenLeak(): void
    {
        foreach ($this->publisher->privateMessages as $msg) {
            $this->assertStringNotContainsString($this->token, $msg['text']);
        }
        foreach ($this->publisher->publishes as $pub) {
            $this->assertStringNotContainsString($this->token, (string) ($pub['text'] ?? ''));
        }
    }

    private function createBaleBot(): Bot
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

    private function createBot(?string $token = null): Bot
    {
        return $this->createBaleBot();
    }

    private function createDestination(
        int $botId,
        string $platform,
        string $tag,
        array $attributes = []
    ): ChannelPosterDestination {
        return ChannelPosterDestination::create(array_merge([
            'bot_id' => $botId,
            'platform' => $platform,
            'channel_chat_id' => $platform . '-channel-1',
            'channel_title' => ucfirst($platform) . ' Channel',
            'is_active' => true,
            'tag' => $tag,
        ], $attributes));
    }
}
