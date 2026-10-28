<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Http\Controllers\ChannelPosterBotController;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use App\Services\ChannelPosterMediaStore;
use Mockery;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * CP-PURE-URL-SIGNATURE: امضای مستقل «متن دلخواه کاربر + URL خالص کانال».
 *
 * Mandatory coverage:
 *   1. line 1 = user text (as-is), line 2 = full channel URL
 *   2. URL always belongs to the same destination (per-destination own link)
 *   3. output contains no <a href=...> and no [label](URL)
 *   4. invalid/missing URL → clear error, wizard stops, nothing fabricated
 *   5. no duplicate link in the output
 *   6. text publishing AND media-caption publishing still work
 *   7. regression covered by the existing signature suites (run in full)
 */
class ChannelPosterPureUrlSignatureTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const SOROUSH_TOKEN = '1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcd1234';

    private FakeChannelPosterPublisher $publisher;

    private string $token = '123:channel-poster-token';

    private string $ownerChatId = '111';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();

        $this->publisher = new FakeChannelPosterPublisher();
        $this->tempFiles = [];
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir(storage_path('app/channel-poster-media-test'));

        Mockery::close();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // 1. Wizard happy path: text line 1 + bare URL line 2 (mission example)
    // ------------------------------------------------------------------
    public function test_wizard_saves_pure_url_and_publishes_text_then_bare_url_as_is(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => 'https://eitaa.com/example_channel',
        ]);
        $userText = 'برای مطالب بیشتر، کانال ما را دنبال کنید.';

        // single tag → straight to channel picker
        $this->postCallback('cp:pururl');
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CHANNEL);
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_pick_channel'),
            $this->publisher->lastPrivateText()
        );

        // pick the channel → asks for the arbitrary text
        $this->postCallback('cp:purch:' . $dest->id);
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_TEXT);
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_ask_text', ['link' => 'https://eitaa.com/example_channel']),
            $this->publisher->lastPrivateText()
        );

        // send the text → preview confirm
        $this->postText($userText);
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CONFIRM);
        $this->assertStringContainsString(
            '«' . $userText . "\n" . 'https://eitaa.com/example_channel»',
            $this->publisher->lastPrivateText()
        );

        // confirm yes → saved, state cleared
        $this->postCallback('cp:purconf:yes');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_saved', ['link' => 'https://eitaa.com/example_channel']),
            $this->publisher->lastPrivateText()
        );

        $dest->refresh();
        $this->assertSame('pure_url', $dest->signature_type);
        $this->assertSame($userText, $dest->signature_label);
        $this->assertSame('https://eitaa.com/example_channel', $dest->channel_link);
        $this->assertTrue((bool) $dest->signature_enabled);
        $this->assertNull($dest->signature_settings, 'pure_url must be independent of smart settings');

        // Publish flow: legacy policy → signature question row is offered.
        $this->postText('سلام');
        $this->assertStringContainsString(
            trans('bot.channel_poster_ask_publish_options'),
            $this->publisher->lastPrivateText()
        );
        $this->assertContains('cp:sig:yes', $this->lastPrivateCallbackData());

        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:now');

        $this->assertCount(1, $this->publisher->publishes);
        $pub = $this->publisher->publishes[0];
        $this->assertSame('eitaa', $pub['platform']);
        $this->assertSame(
            'سلام' . "\n\n" . $userText . "\n" . 'https://eitaa.com/example_channel',
            $pub['text'],
            'line 1 = user text as-is, line 2 = full channel URL, plain output'
        );
        $this->assertNull($pub['parse_mode'], 'pure_url is plain text on every platform');
        $this->assertNull($pub['reply_markup']);
        $this->assertNoState();
        $this->assertNoTokenLeak();
    }

    // ------------------------------------------------------------------
    // 2. No valid URL → clear error, wizard stops, nothing written,
    //    no fabricated link.
    // ------------------------------------------------------------------
    public function test_destination_without_valid_url_stops_wizard_and_changes_nothing(): void
    {
        $bot = $this->createBot();
        $noLink = $this->createDestination($bot->id, 'bale', 'tag1'); // channel_link = null
        $badLink = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'example_channel', // no http(s) scheme — not a public URL
        ]);

        // missing link
        $this->postCallback('cp:pururl');
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CHANNEL);
        $this->postCallback('cp:purch:' . $noLink->id);
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_no_link'),
            $this->publisher->lastPrivateText()
        );

        // invalid-scheme link
        $this->postCallback('cp:pururl');
        $this->postCallback('cp:purch:' . $badLink->id);
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_no_link'),
            $this->publisher->lastPrivateText()
        );

        // nothing was written, no signature was fabricated
        // (signature_type model default is 'inline' — it must NOT become 'pure_url')
        $this->assertNotSame('pure_url', $noLink->refresh()->signature_type);
        $this->assertNotSame('pure_url', $badLink->refresh()->signature_type);
        $this->assertNull($noLink->refresh()->signature_label);
        $this->assertNull($badLink->refresh()->signature_label);
        $this->assertNull($noLink->refresh()->signature_enabled);
        $this->assertNull($badLink->refresh()->signature_enabled);

        // publishing afterwards never produces a link for these destinations
        $this->postText('سلام');
        $this->postCallback('cp:now');
        $this->assertCount(2, $this->publisher->publishes);
        foreach ($this->publisher->publishes as $pub) {
            $this->assertStringNotContainsString('http', $pub['text']);
            $this->assertStringNotContainsString('example_channel', $pub['text']);
        }
    }

    // ------------------------------------------------------------------
    // 3. Per-destination own URL — each channel signs with its OWN link
    // ------------------------------------------------------------------
    public function test_each_destination_signs_with_only_its_own_url(): void
    {
        $bot = $this->createBot();
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/chA',
        ]);
        $eitaa = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => 'https://eitaa.com/chB',
        ]);

        // configure the bale destination
        $this->runPureUrlWizard($bale, 'لینک بله ما');
        // configure the eitaa destination
        $this->runPureUrlWizard($eitaa, 'لینک ایتا ما');

        $this->postText('خبر امروز');
        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:now');

        $this->assertCount(2, $this->publisher->publishes);
        $byPlatform = [];
        foreach ($this->publisher->publishes as $pub) {
            $byPlatform[$pub['platform']] = $pub;
        }

        $this->assertSame(
            'خبر امروز' . "\n\nلینک بله ما\nhttps://ble.ir/chA",
            $byPlatform['bale']['text'],
            'bale destination must sign with its own URL only'
        );
        $this->assertSame(
            'خبر امروز' . "\n\nلینک ایتا ما\nhttps://eitaa.com/chB",
            $byPlatform['eitaa']['text'],
            'eitaa destination must sign with its own URL only'
        );
        $this->assertStringNotContainsString('https://eitaa.com/chB', $byPlatform['bale']['text']);
        $this->assertStringNotContainsString('https://ble.ir/chA', $byPlatform['eitaa']['text']);
    }

    // ------------------------------------------------------------------
    // 4. Output format guarantees: no anchor, no markdown link,
    //    exactly one occurrence of the URL, text preserved byte-for-byte.
    // ------------------------------------------------------------------
    public function test_published_output_has_no_anchor_no_markdown_and_single_url(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/pure',
        ]);
        $userText = 'فروشگاه ما & فروشگاه شما [تخفیف] (۲۰٪) #تخفیف';

        $this->runPureUrlWizard($dest, $userText);

        $this->postText('پست');
        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:now');

        $this->assertCount(1, $this->publisher->publishes);
        $pub = $this->publisher->publishes[0];
        $this->assertSame('bale', $pub['platform']);

        $this->assertStringNotContainsString('<a href', $pub['text']);
        $this->assertStringNotContainsString('</a>', $pub['text']);
        $this->assertStringNotContainsString('](', $pub['text'], 'no markdown [label](URL) link');
        $this->assertStringNotContainsString('&', $pub['text'], 'plain output — no HTML escaping');

        $this->assertSame(1, substr_count($pub['text'], 'https://ble.ir/pure'), 'URL must appear exactly once (no duplicate)');
        $this->assertStringContainsString(
            "\n\n" . $userText . "\n",
            $pub['text'],
            'user text is preserved byte-for-byte as the first signature line'
        );
        $this->assertNull($pub['parse_mode']);
        $this->assertNoTokenLeak();
    }

    // ------------------------------------------------------------------
    // 5. Media (photo caption) publishing through the queue still works
    // ------------------------------------------------------------------
    public function test_queue_photo_caption_publish_appends_pure_url_signature(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/purech',
        ]);
        $userText = 'متن امضا برای عکس';
        $this->runPureUrlWizard($dest, $userText);

        // incoming photo with caption (origin: bale → file must be downloaded)
        $mediaPath = $this->makeTempFile('webhook-pure.jpg', 'pure-photo-bytes');
        $store = Mockery::mock(ChannelPosterMediaStore::class)->makePartial();
        $store->shouldReceive('fetchAndStore')
            ->once()
            ->with($this->token, 'photo-file-id-123', 'bale', 'photo', null)
            ->andReturn($mediaPath);
        $this->app->instance(ChannelPosterMediaStore::class, $store);

        $this->postPhotoUpdate('photo-file-id-123', 'کپشن عکس تست');
        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:queue');

        $item = ChannelPosterQueue::first();
        $this->assertNotNull($item);
        $this->assertTrue((bool) $item->signature_enabled);

        // --all: the next slot is in the future, so the item would otherwise
        // not be picked up as "due" by the command.
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id, '--all' => true])
            ->assertSuccessful();

        $this->assertCount(1, $this->publisher->publishes);
        $pub = $this->publisher->publishes[0];
        $this->assertSame('photo', $pub['content_type']);
        $this->assertSame($mediaPath, $pub['local_path']);
        $this->assertSame(
            'کپشن عکس تست' . "\n\n" . $userText . "\n" . 'https://t.me/purech',
            $pub['text'],
            'caption keeps the pure_url signature appended (text then bare URL)'
        );
        $this->assertNull($pub['parse_mode']);
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->refresh()->status);
        $this->assertNoTokenLeak();
    }

    // ------------------------------------------------------------------
    // 6. Native (soroush) origin: full wizard by typed numbers/words,
    //    text hints shown, publish renders the same plain output.
    // ------------------------------------------------------------------
    public function test_wizard_via_soroush_text_path_and_plain_publish(): void
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
        $userText = 'متن امضای سروش';

        // enter via callback, then every step by text (keyboard is stripped for native)
        $this->postSoroushCallback('cp:pururl');
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CHANNEL, 'soroush');

        $this->postSoroushText('1'); // channel number
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_TEXT, 'soroush');
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_ask_text', ['link' => 'https://soroush.me/ch']),
            $this->publisher->lastPrivateText()
        );

        // the text step (ask_text) has no buttons → no hint; the confirm step
        // does (yes/no/back) → the native text hint must be appended there
        $this->postSoroushText('متن میانی');
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CONFIRM, 'soroush');
        $this->assertStringContainsString(
            trans('bot.channel_poster_platform_native_hint'),
            $this->publisher->lastPrivateText()
        );

        $this->postSoroushText($userText);
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CONFIRM, 'soroush');
        $this->assertStringContainsString(
            '«' . $userText . "\n" . 'https://soroush.me/ch»',
            $this->publisher->lastPrivateText()
        );

        $this->postSoroushText('بله');
        $this->assertNoState();
        $soroush->refresh();
        $this->assertSame('pure_url', $soroush->signature_type);
        $this->assertSame($userText, $soroush->signature_label);
        $this->assertTrue((bool) $soroush->signature_enabled);

        // publish flow
        $this->postSoroushText('سلام سروش');
        $this->assertStringContainsString(
            trans('bot.channel_poster_ask_publish_options'),
            $this->publisher->lastPrivateText()
        );
        $this->postSoroushCallback('cp:sig:yes');
        $this->postSoroushCallback('cp:now');

        $this->assertCount(1, $this->publisher->publishes);
        $pub = $this->publisher->publishes[0];
        $this->assertSame('soroush', $pub['platform']);
        $this->assertSame(
            'سلام سروش' . "\n\n" . $userText . "\n" . 'https://soroush.me/ch',
            $pub['text']
        );
        $this->assertNull($pub['parse_mode']);
        $this->assertNoTokenLeak();
    }

    // ------------------------------------------------------------------
    // 7. /cancel mid-wizard: previous settings untouched
    // ------------------------------------------------------------------
    public function test_cancel_mid_wizard_keeps_previous_settings(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/old',
            'signature_type' => 'inline',
            'signature_label' => 'کانال قدیمی',
            'signature_enabled' => true,
        ]);

        $this->postCallback('cp:pururl');
        $this->postCallback('cp:purch:' . $dest->id);
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_TEXT);

        $this->postText('/cancel');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_cancelled'),
            $this->publisher->lastPrivateText()
        );

        $dest->refresh();
        $this->assertSame('inline', $dest->signature_type, 'cancel must not touch previous signature');
        $this->assertSame('کانال قدیمی', $dest->signature_label);
        $this->assertTrue((bool) $dest->signature_enabled);
    }

    // ------------------------------------------------------------------
    // 8. Confirm step details: invalid answer keeps state; 'no' saves
    //    disabled; 'back' re-asks for the text.
    // ------------------------------------------------------------------
    public function test_confirm_invalid_no_and_back_behaviors(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
        ]);

        // reach the confirm step
        $this->postCallback('cp:pururl');
        $this->postCallback('cp:purch:' . $dest->id);
        $this->postText('متن اول');
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CONFIRM);

        // invalid answer → stays on confirm
        $this->postText('هرچه دلم خواست');
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_CONFIRM);
        $this->assertStringContainsString(
            trans('bot.channel_poster_pur_confirm_invalid'),
            $this->publisher->lastPrivateText()
        );

        // back → re-asks for the text
        $this->postText('بازگشت');
        $this->assertState(ChannelPosterBotController::STATE_PURE_URL_TEXT);

        // change the text, then answer 'no' (disabled)
        $this->postText('متن جدید');
        $this->postText('نه');
        $this->assertNoState();

        $dest->refresh();
        $this->assertSame('pure_url', $dest->signature_type);
        $this->assertSame('متن جدید', $dest->signature_label);
        $this->assertFalse((bool) $dest->signature_enabled, 'خیر → signature disabled, not erased');
    }

    // ------------------------------------------------------------------
    // 9. Settings menu exposes the Pure URL entry (owner)
    // ------------------------------------------------------------------
    public function test_settings_menu_shows_pure_url_entry(): void
    {
        $this->createBot();
        $this->postText('/settings');

        $this->assertContains(
            'cp:pururl',
            $this->lastPrivateCallbackData(),
            'owner menu must contain the Pure URL wizard entry'
        );
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * Run the Pure URL wizard (callback path) for one destination and
     * save it with the given text. Ends with the "saved" message.
     */
    private function runPureUrlWizard(ChannelPosterDestination $dest, string $text): void
    {
        $this->postCallback('cp:pururl');
        $this->postCallback('cp:purch:' . $dest->id);
        $this->postText($text);
        $this->postCallback('cp:purconf:yes');
        $this->assertNoState();
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

    private function postPhotoUpdate(string $fileId, string $caption): void
    {
        $this->postJson('/api/webhook-channel-poster?origin=bale&token=' . $this->token, [
            'update_id' => 3,
            'message' => [
                'message_id' => 20,
                'from' => ['id' => (int) $this->ownerChatId, 'is_bot' => false],
                'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                'date' => time(),
                'photo' => [
                    [
                        'file_id' => 'smallest-photo-id',
                        'file_unique_id' => 'u1',
                        'width' => 90,
                        'height' => 90,
                        'file_size' => 1000,
                    ],
                    [
                        'file_id' => $fileId,
                        'file_unique_id' => 'u2',
                        'width' => 800,
                        'height' => 600,
                        'file_size' => 90000,
                    ],
                ],
                'caption' => $caption,
            ],
        ])->assertOk();
    }

    private function assertState(string $state, ?string $origin = null): void
    {
        $bot = Bot::first();
        $query = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id);
        if ($origin !== null) {
            $query->where('origin', $origin);
        }
        $user = $query->first();
        $this->assertNotNull($user, 'bot user row must exist for state assertion');
        $this->assertSame(
            $state,
            BotUserState::where('bot_user_id', $user->id)->value('state'),
            'expected state: ' . $state
        );
    }

    private function assertNoState(): void
    {
        $bot = Bot::first();
        $user = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
        if (!$user) {
            $this->assertSame(0, BotUserState::count());

            return;
        }
        $this->assertNull(BotUserState::where('bot_user_id', $user->id)->value('state'), 'state must be cleared');
    }

    /** Flattened callback_data of the last private-message keyboard. */
    private function lastPrivateCallbackData(): array
    {
        return array_merge([], ...array_map(
            fn (array $row) => array_column($row, 'callback_data'),
            $this->lastPrivateKeyboard()
        ));
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

    private function makeTempFile(string $name, string $contents): string
    {
        $dir = storage_path('app/channel-poster-media-test');
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            $this->fail('could not create temp media directory');
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
