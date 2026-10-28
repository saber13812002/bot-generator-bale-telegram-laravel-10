<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Http\Controllers\ChannelPosterBotController;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * CP-CHANNEL-HANDLE-SIGNATURE: امضای «ایموجی + شناسهٔ کوتاه کانال».
 *
 * Mandatory coverage (mission §6):
 *   1. telegram: valid short handle extracted from its own channel_link
 *   2. each destination signs from its OWN valid data (link or username)
 *   3. full channel URL is NOT repeated in the output
 *   4. no raw HTML anchor / Markdown link / any http in the signature
 *   5. main text unchanged, signature on the last line
 *   6. no valid handle → wizard stops, nothing fabricated, no signature
 *   7. handle mode coexists with pure_url on other destinations of the tag
 *   8. native (soroush) origin: full wizard by typed numbers/words
 *   9. /cancel mid-wizard keeps previous settings
 *   10. regression of previous signature/publish policies is covered by the
 *       existing signature suites (run in full)
 */
class ChannelPosterHandleSignatureTest extends TestCase
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
    // 1. Wizard happy path: telegram short handle, plain «🔰@handle» line
    // ------------------------------------------------------------------
    public function test_wizard_saves_handle_and_publishes_plain_emoji_handle_line(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/saberblog/42',
        ]);

        // single tag → straight to channel picker
        $this->postCallback('cp:handle');
        $this->assertState(ChannelPosterBotController::STATE_HANDLE_CHANNEL);
        $this->assertStringContainsString(
            trans('bot.channel_poster_hnd_pick_channel'),
            $this->publisher->lastPrivateText()
        );

        // pick the channel → asks for the emoji (handle is shown in the prompt)
        $this->postCallback('cp:hndch:' . $dest->id);
        $this->assertState(ChannelPosterBotController::STATE_HANDLE_EMOJI);
        $this->assertStringContainsString('saberblog', $this->publisher->lastPrivateText());

        // «default» → default emoji 🔰
        $this->postText('default');
        $this->assertState(ChannelPosterBotController::STATE_HANDLE_CONFIRM);
        $this->assertStringContainsString(
            '«🔰@saberblog»',
            $this->publisher->lastPrivateText()
        );

        // confirm yes → saved, state cleared
        $this->postCallback('cp:hndconf:yes');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_hnd_saved', ['preview' => '🔰@saberblog']),
            $this->publisher->lastPrivateText()
        );

        $dest->refresh();
        $this->assertSame('handle', $dest->signature_type);
        $this->assertNull($dest->signature_label, 'default emoji must be stored as null label');
        $this->assertTrue((bool) $dest->signature_enabled);
        $this->assertNull($dest->signature_settings, 'handle signature must be independent of smart settings');
        $this->assertSame('https://t.me/saberblog/42', $dest->channel_link, 'the stored link must not be touched');

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
        $this->assertSame('telegram', $pub['platform']);
        $this->assertSame('سلام' . "\n\n🔰@saberblog", $pub['text']);
        $this->assertNull($pub['parse_mode'], 'handle signature is plain text on every platform');
        $this->assertNull($pub['reply_markup']);

        // format guarantees (§4/§6): no anchor, no markdown link, no URL at all
        $this->assertStringNotContainsString('<a', $pub['text']);
        $this->assertStringNotContainsString('](', $pub['text']);
        $this->assertStringNotContainsString('t.me', $pub['text'], 'the full channel URL must not be re-added');
        $this->assertStringStartsWith('سلام', $pub['text'], 'main text must stay unchanged');
        $this->assertNoState();
        $this->assertNoTokenLeak();
    }

    // ------------------------------------------------------------------
    // 2. Custom emoji stored in signature_label and used at publish time
    // ------------------------------------------------------------------
    public function test_custom_emoji_is_stored_in_signature_label_and_used(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/saberblog/42',
        ]);

        $this->runHandleWizard($dest, '⭐');

        $dest->refresh();
        $this->assertSame('handle', $dest->signature_type);
        $this->assertSame('⭐', $dest->signature_label);
        $this->assertTrue((bool) $dest->signature_enabled);

        $this->postText('سلام');
        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:now');

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('سلام' . "\n\n⭐@saberblog", $this->publisher->publishes[0]['text']);
        $this->assertNull($this->publisher->publishes[0]['parse_mode']);
    }

    // ------------------------------------------------------------------
    // 3. Per-destination own data: each channel signs from ITS OWN data
    // ------------------------------------------------------------------
    public function test_each_destination_signs_with_only_its_own_handle(): void
    {
        $bot = $this->createBot();
        $tg = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/saberblog/42',
        ]);
        $eitaa = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => 'https://eitaa.com/alirezamehran_3/1182',
        ]);
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_username' => 'stackoverflow_com', // valid trusted username, no link
        ]);

        $this->runHandleWizard($tg);
        $this->runHandleWizard($eitaa);
        $this->runHandleWizard($bale);

        $this->postText('خبر امروز');
        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:now');

        $this->assertCount(3, $this->publisher->publishes);
        $byPlatform = [];
        foreach ($this->publisher->publishes as $pub) {
            $byPlatform[$pub['platform']] = $pub;
        }

        $this->assertSame(
            'خبر امروز' . "\n\n🔰@saberblog",
            $byPlatform['telegram']['text'],
            'telegram signs from its own channel_link'
        );
        $this->assertSame(
            'خبر امروز' . "\n\n🔰@alirezamehran_3",
            $byPlatform['eitaa']['text'],
            'eitaa signs from its own channel_link'
        );
        $this->assertSame(
            'خبر امروز' . "\n\n🔰@stackoverflow_com",
            $byPlatform['bale']['text'],
            'bale signs from its own trusted channel_username even without a link'
        );

        // no destination may leak another destination's data
        $this->assertStringNotContainsString('alirezamehran_3', $byPlatform['telegram']['text']);
        $this->assertStringNotContainsString('saberblog', $byPlatform['bale']['text']);
        foreach ($this->publisher->publishes as $pub) {
            $this->assertStringNotContainsString('http', $pub['text'], 'handle output must contain no URL');
            $this->assertStringNotContainsString('<a', $pub['text']);
            $this->assertStringNotContainsString('](', $pub['text']);
        }
    }

    // ------------------------------------------------------------------
    // 4. No valid handle → clear error, wizard stops, nothing fabricated,
    //    publishing afterwards produces no signature for those channels.
    // ------------------------------------------------------------------
    public function test_destinations_without_valid_handle_stop_wizard_and_publish_plain(): void
    {
        $bot = $this->createBot();
        $rubika = $this->createDestination($bot->id, 'rubika', 'tag1'); // no link, no username
        $tg = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/c/12345/42', // private c/ path — not a public handle
        ]);
        $bale = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_username' => '1234567890', // all numeric = chat id, not a username
        ]);

        foreach ([$rubika, $tg, $bale] as $dest) {
            $this->postCallback('cp:handle');
            $this->assertState(ChannelPosterBotController::STATE_HANDLE_CHANNEL);
            $this->postCallback('cp:hndch:' . $dest->id);
            $this->assertNoState();
            $this->assertStringContainsString(
                trans('bot.channel_poster_hnd_no_handle'),
                $this->publisher->lastPrivateText()
            );
        }

        // nothing was written, no handle signature was fabricated
        foreach ([$rubika, $tg, $bale] as $dest) {
            $dest->refresh();
            $this->assertNotSame('handle', $dest->signature_type);
            $this->assertNull($dest->signature_label);
        }

        // publishing afterwards: plain text, no fabricated handle, no c/ id leaked
        $this->postText('سلام');
        $this->postCallback('cp:now');
        $this->assertCount(3, $this->publisher->publishes);
        foreach ($this->publisher->publishes as $pub) {
            $this->assertSame('سلام', $pub['text']);
            $this->assertStringNotContainsString('@', $pub['text']);
            $this->assertStringNotContainsString('12345', $pub['text']);
        }
    }

    // ------------------------------------------------------------------
    // 5. Handle mode and pure_url coexist per destination within one tag
    // ------------------------------------------------------------------
    public function test_handle_and_pure_url_coexist_per_destination(): void
    {
        $bot = $this->createBot();
        $tg = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/saberblog/42',
        ]);
        $eitaa = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => 'https://eitaa.com/example_channel',
        ]);

        $this->runHandleWizard($tg);
        $this->runPureUrlWizard($eitaa, 'لینک ایتا ما');

        $this->postText('متن');
        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:now');

        $this->assertCount(2, $this->publisher->publishes);
        $byPlatform = [];
        foreach ($this->publisher->publishes as $pub) {
            $byPlatform[$pub['platform']] = $pub;
        }

        $this->assertSame(
            'متن' . "\n\n🔰@saberblog",
            $byPlatform['telegram']['text'],
            'handle destination keeps the plain emoji+handle signature'
        );
        $this->assertSame(
            'متن' . "\n\nلینک ایتا ما\nhttps://eitaa.com/example_channel",
            $byPlatform['eitaa']['text'],
            'pure_url destination keeps the old text+URL signature'
        );
        $this->assertStringNotContainsString('example_channel', $byPlatform['telegram']['text']);
        $this->assertStringNotContainsString('🔰', $byPlatform['eitaa']['text']);
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
            'channel_link' => 'https://soroush.chat/foo/9',
        ]);

        // enter via callback, then every step by text (keyboard is stripped for native)
        $this->postSoroushCallback('cp:handle');
        $this->assertState(ChannelPosterBotController::STATE_HANDLE_CHANNEL, 'soroush');

        $this->postSoroushText('1'); // channel number
        $this->assertState(ChannelPosterBotController::STATE_HANDLE_EMOJI, 'soroush');
        $this->assertStringContainsString(
            trans('bot.channel_poster_hnd_ask_emoji', ['handle' => 'foo']),
            $this->publisher->lastPrivateText()
        );

        // the confirm step has buttons (yes/no/back) → the native text hint
        // must be appended there
        $this->postSoroushText('⭐');
        $this->assertState(ChannelPosterBotController::STATE_HANDLE_CONFIRM, 'soroush');
        $this->assertStringContainsString('«⭐@foo»', $this->publisher->lastPrivateText());
        $this->assertStringContainsString(
            trans('bot.channel_poster_platform_native_hint'),
            $this->publisher->lastPrivateText()
        );

        $this->postSoroushText('بله');
        $this->assertNoState();
        $soroush->refresh();
        $this->assertSame('handle', $soroush->signature_type);
        $this->assertSame('⭐', $soroush->signature_label);
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
        $this->assertSame('سلام سروش' . "\n\n⭐@foo", $pub['text']);
        $this->assertStringNotContainsString('soroush.chat', $pub['text'], 'no full URL in the output');
        $this->assertNull($pub['parse_mode']);
        $this->assertNoTokenLeak();
    }

    // ------------------------------------------------------------------
    // 7. /cancel mid-wizard: previous settings untouched
    // ------------------------------------------------------------------
    public function test_cancel_mid_wizard_keeps_previous_settings(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/saberblog/42',
            'signature_type' => 'inline',
            'signature_label' => 'کانال قدیمی',
            'signature_enabled' => true,
        ]);

        $this->postCallback('cp:handle');
        $this->postCallback('cp:hndch:' . $dest->id);
        $this->assertState(ChannelPosterBotController::STATE_HANDLE_EMOJI);

        $this->postText('/cancel');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_hnd_cancelled'),
            $this->publisher->lastPrivateText()
        );

        $dest->refresh();
        $this->assertSame('inline', $dest->signature_type, 'cancel must not touch previous signature');
        $this->assertSame('کانال قدیمی', $dest->signature_label);
        $this->assertTrue((bool) $dest->signature_enabled);
    }

    // ------------------------------------------------------------------
    // 8. Settings menu exposes the handle entry (owner)
    // ------------------------------------------------------------------
    public function test_settings_menu_shows_handle_entry(): void
    {
        $this->createBot();
        $this->postText('/settings');

        $this->assertContains(
            'cp:handle',
            $this->lastPrivateCallbackData(),
            'owner menu must contain the emoji+handle wizard entry'
        );
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * Run the handle-signature wizard (callback path) for one destination.
     * Single tag → channel picker → channel → emoji text → confirm yes.
     */
    private function runHandleWizard(ChannelPosterDestination $dest, string $emoji = 'default'): void
    {
        $this->postCallback('cp:handle');
        $this->postCallback('cp:hndch:' . $dest->id);
        $this->postText($emoji);
        $this->postCallback('cp:hndconf:yes');
        $this->assertNoState();
    }

    /**
     * Run the Pure URL wizard (callback path) for one destination.
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
}
