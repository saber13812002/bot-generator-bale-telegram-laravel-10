<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelPosterBotController;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use App\Services\ChannelPoster\SignatureRenderer;
use App\Services\ChannelPoster\SignatureSettingsService;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * CP-RELEASE-GATE — signature state transitions through the real webhook
 * (controller-level, FakeChannelPosterPublisher).
 *
 * Covers the release-gate behaviors end-to-end:
 *   F1. pure_url -> handle wizard transition: the published post switches to
 *       the handle line; the stale pure_url text/URL must never render again
 *   F2. handle -> smart wizard transition: the published post switches to the
 *       multi-line smart signature; the stale handle emoji/handle must never
 *       render; the legacy cp:sig:yes question must disappear
 *   F3. smart -> pure_url wizard transition: the published post returns to the
 *       plain pure-URL signature; the stale smart anchor/label must never
 *       render; the legacy question must come back
 *   F4. /deletepurelink wizard matrix (pure_url vs handle) + publish:
 *       pure_url removal leaves type=none (no signature at all), the handle
 *       destination stays untouched and keeps signing
 *
 * Every published-text assertion is byte-exact (assertSame).
 */
class SignatureStateTransitionFeatureTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const TG_URL = 'https://t.me/saberblog/42';
    private const TG_NEWS = 'https://t.me/sabernews/7';
    private const EITAA_URL = 'https://eitaa' . 'link.example/5';

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

    // ─────────────────────────────────────────────────────────────────────
    // F1 — pure_url -> handle
    // ─────────────────────────────────────────────────────────────────────

    public function test_pure_url_to_handle_transition_via_wizard_publishes_new_signature(): void
    {
        $bot = $this->createBot();
        $a = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => self::TG_URL,
        ]);
        $b = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => self::EITAA_URL,
        ]);

        // ── wizard 1: pure_url on A ────────────────────────────────────
        $this->runPureUrlWizard($a, 'متن خداحافظی');
        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_PURE_URL, $aF->signature_type);
        $this->assertSame('متن خداحافظی', $aF->signature_label);
        $this->assertSame(true, $aF->signature_enabled);
        $this->assertNull($aF->signature_settings);
        $this->assertSame(self::TG_URL, $aF->channel_link);

        // publish #1 — legacy signature question appears
        $this->publishNow(true);
        $pubA1 = $this->publishByPlatform('telegram');
        $this->assertSame('سلام' . "\n\nمتن خداحافظی\n" . self::TG_URL, $pubA1['text']);
        $this->assertNull($pubA1['parse_mode']);
        $pubB1 = $this->publishByPlatform('eitaa');
        $this->assertSame('سلام' . "\n\nدر ایتا:\n" . self::EITAA_URL, $pubB1['text']);
        $this->assertNull($pubB1['parse_mode']);

        // ── wizard 2: handle on the SAME destination A ─────────────────
        $this->runHandleWizard($a);
        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $aF->signature_type);
        $this->assertNull($aF->signature_label, 'default emoji is stored as null label');
        $this->assertSame(true, $aF->signature_enabled);
        $this->assertNull($aF->signature_settings);
        $this->assertSame(self::TG_URL, $aF->channel_link);

        // publish #2 — same legacy question (A still has a link)
        $this->publishNow(true);
        $pubA2 = $this->publishByPlatform('telegram');
        $this->assertSame('سلام' . "\n\n🔰 @saberblog", $pubA2['text']);
        $this->assertNull($pubA2['parse_mode']);
        $this->assertStringNotContainsString('متن خداحافظی', $pubA2['text'], 'stale pure_url text must not render');
        $this->assertStringNotContainsString('t.me', $pubA2['text'], 'stale pure_url URL must not render');
        $this->assertStringNotContainsString('<a', $pubA2['text']);
        $this->assertStringNotContainsString('](', $pubA2['text']);

        // tagmate B: byte-identical to publish #1
        $this->assertSame($pubB1, $this->publishByPlatform('eitaa'));

        $this->assertNoState();
        $this->assertNoTokenLeak();
    }

    // ─────────────────────────────────────────────────────────────────────
    // F2 — handle -> smart
    // ─────────────────────────────────────────────────────────────────────

    public function test_handle_to_smart_transition_via_wizard_changes_publish_output(): void
    {
        $bot = $this->createBot();
        $a = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => self::TG_NEWS,
        ]);
        $b = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => self::EITAA_URL,
            'signature_type' => SignatureRenderer::TYPE_INLINE,
            'signature_label' => 'برچسب بی',
        ]);

        $this->runHandleWizard($a, '⭐');
        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $aF->signature_type);
        $this->assertSame('⭐', $aF->signature_label);

        // publish #1 — legacy handle line
        $this->publishNow(true);
        $this->assertSame('سلام' . "\n\n⭐ @sabernews", $this->publishByPlatform('telegram')['text']);
        $pubB1 = $this->publishByPlatform('eitaa');
        $this->assertSame('سلام' . "\n\nبرچسب بی:\n" . self::EITAA_URL, $pubB1['text']);

        // ── smart wizard on A: all_channels + multi_line + always_apply ─
        $this->runSmartSigWizard($a);
        $aF = $a->fresh();
        // stale type/label remain by design — smart settings win at render time
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $aF->signature_type);
        $this->assertSame('⭐', $aF->signature_label);
        $this->assertSame(true, $aF->signature_enabled);
        $expectedSettings = SignatureSettingsService::normalize([
            'enabled' => true,
            'link_selection_policy' => SignatureSettingsService::POLICY_ALL_CHANNELS,
            'layout' => SignatureSettingsService::LAYOUT_MULTI_LINE,
            'separator' => SignatureSettingsService::SEPARATOR_DEFAULT,
            'selected_destinations' => [],
            'random_count' => 2,
            'application_policy' => SignatureSettingsService::APPLY_ALWAYS,
        ]);
        $this->assertSame($expectedSettings, $aF->signature_settings);

        // publish #2 — always_apply: NO legacy signature question
        $this->publishNow(false);
        $pubA2 = $this->publishByPlatform('telegram');
        $expectedSig = "\n\nبرچسب بی:\n<a href=\"" . self::EITAA_URL . "\">برچسب بی</a>\nدر تلگرام:\n<a href=\"" . self::TG_NEWS . "\">در تلگرام</a>";
        $this->assertSame('سلام' . $expectedSig, $pubA2['text']);
        $this->assertSame('html', $pubA2['parse_mode']);
        $this->assertStringNotContainsString('⭐ @sabernews', $pubA2['text'], 'stale handle line must not render');

        // tagmate B: byte-identical to publish #1
        $this->assertSame($pubB1, $this->publishByPlatform('eitaa'));

        $this->assertNoState();
        $this->assertNoTokenLeak();
    }

    // ─────────────────────────────────────────────────────────────────────
    // F3 — smart -> pure_url
    // ─────────────────────────────────────────────────────────────────────

    public function test_smart_to_pure_url_transition_via_wizard_restores_plain_signature(): void
    {
        $bot = $this->createBot();
        $a = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => self::TG_URL,
            'signature_type' => SignatureRenderer::TYPE_INLINE,
            'signature_label' => 'برچسب هوشمند',
            'signature_enabled' => true,
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => SignatureSettingsService::POLICY_ALL_CHANNELS,
                'layout' => SignatureSettingsService::LAYOUT_SINGLE_LINE,
                'separator' => SignatureSettingsService::SEPARATOR_DEFAULT,
            ]),
        ]);
        $b = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => self::EITAA_URL,
        ]);

        // publish #1 — smart single_line, always_apply (no legacy question)
        $this->publishNow(false);
        $pubA1 = $this->publishByPlatform('telegram');
        $expectedSig = "\n\n<a href=\"" . self::EITAA_URL . "\">در ایتا</a> | <a href=\"" . self::TG_URL . "\">برچسب هوشمند</a>";
        $this->assertSame('سلام' . $expectedSig, $pubA1['text']);
        $this->assertSame('html', $pubA1['parse_mode']);
        $pubB1 = $this->publishByPlatform('eitaa');
        $this->assertSame('سلام' . "\n\nدر ایتا:\n" . self::EITAA_URL, $pubB1['text']);

        // ── pure_url wizard on the SAME destination A ───────────────────
        $this->runPureUrlWizard($a, 'متن خداحافظی');
        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_PURE_URL, $aF->signature_type);
        $this->assertSame('متن خداحافظی', $aF->signature_label);
        $this->assertSame(true, $aF->signature_enabled);
        $this->assertNull($aF->signature_settings, 'pure_url save must clear the stale smart settings');
        $this->assertSame(self::TG_URL, $aF->channel_link);

        // publish #2 — legacy question comes back (no smart destination left)
        $this->publishNow(true);
        $pubA2 = $this->publishByPlatform('telegram');
        $this->assertSame('سلام' . "\n\nمتن خداحافظی\n" . self::TG_URL, $pubA2['text']);
        $this->assertNull($pubA2['parse_mode']);
        $this->assertStringNotContainsString('برچسب هوشمند', $pubA2['text'], 'stale smart label must not render');
        $this->assertStringNotContainsString('<a', $pubA2['text'], 'stale smart anchor must not render');
        $this->assertStringNotContainsString(self::EITAA_URL, $pubA2['text'], 'stale smart multi-link must not render');

        // tagmate B: byte-identical to publish #1
        $this->assertSame($pubB1, $this->publishByPlatform('eitaa'));

        $this->assertNoState();
        $this->assertNoTokenLeak();
    }

    // ─────────────────────────────────────────────────────────────────────
    // F4 — /deletepurelink wizard matrix (pure_url vs handle) + publish
    // ─────────────────────────────────────────────────────────────────────

    public function test_deletepurelink_wizard_matrix_pure_handle_and_publish(): void
    {
        $bot = $this->createBot();
        $a = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => self::TG_URL,
            'signature_type' => SignatureRenderer::TYPE_PURE_URL,
            'signature_label' => 'متن آ',
            'signature_enabled' => true,
        ]);
        $b = $this->createDestination($bot->id, 'eitaa', 'tag1', [
            'channel_link' => self::EITAA_URL,
            'signature_type' => SignatureRenderer::TYPE_HANDLE,
            'signature_enabled' => true,
            'channel_username' => 'sabernews',
        ]);

        // ── run 1: pure_url destination A ───────────────────────────────
        $this->postText('/deletepurelink');
        $this->assertState(ChannelPosterBotController::STATE_DELETE_PURE_LINK_CHANNEL);
        $this->assertStringContainsString('pure_url', (string) $this->publisher->lastPrivateText(), 'channel list must show the pure_url marker');
        $this->postCallback('cp:dplch:' . $a->id);
        $this->assertState(ChannelPosterBotController::STATE_DELETE_PURE_LINK_CONFIRM);
        $data = $this->lastPrivateCallbackData();
        $this->assertContains('cp:dplconf:pur', $data, 'pure_url destination must offer the delete-purelink button');
        $this->postCallback('cp:dplconf:pur');
        $this->assertNoState();

        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_NONE, $aF->signature_type);
        $this->assertNull($aF->signature_label);
        $this->assertSame(true, $aF->signature_enabled, 'delete-purelink must not flip signature_enabled');
        $this->assertNull($aF->signature_settings);
        $this->assertSame(self::TG_URL, $aF->channel_link);

        // ── run 2: handle destination B — no pur button, cancel ─────────
        $this->postText('/deletepurelink');
        $this->assertState(ChannelPosterBotController::STATE_DELETE_PURE_LINK_CHANNEL);
        $this->postCallback('cp:dplch:' . $b->id);
        $this->assertState(ChannelPosterBotController::STATE_DELETE_PURE_LINK_CONFIRM);
        $data = $this->lastPrivateCallbackData();
        $this->assertNotContains('cp:dplconf:pur', $data, 'non-pure destination must not offer the delete-purelink button');
        $this->assertContains('cp:dplconf:all', $data);
        $this->assertContains('cp:dplconf:cancel', $data);
        $this->postCallback('cp:dplconf:cancel');
        $this->assertNoState();

        $bF = $b->fresh();
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $bF->signature_type, 'cancel must leave the handle signature untouched');
        $this->assertSame('sabernews', $bF->channel_username);
        $this->assertSame(true, $bF->signature_enabled);

        // ── publish: A signs nothing (type none), B keeps its handle ────
        $this->publishNow(true);
        $pubA = $this->publishByPlatform('telegram');
        $this->assertSame('سلام', $pubA['text'], 'type=none destination must publish without any signature');
        $this->assertNull($pubA['parse_mode']);
        $pubB = $this->publishByPlatform('eitaa');
        $this->assertSame('سلام' . "\n\n🔰 @sabernews", $pubB['text']);
        $this->assertNull($pubB['parse_mode']);

        $this->assertNoState();
        $this->assertNoTokenLeak();
    }

    // ─────────────────────────────────────────────────────────────────────
    // wizard runners
    // ─────────────────────────────────────────────────────────────────────

    /** Handle wizard (callback path): channel picker → channel → emoji → yes. */
    private function runHandleWizard(ChannelPosterDestination $dest, string $emoji = 'default'): void
    {
        $this->postCallback('cp:handle');
        $this->postCallback('cp:hndch:' . $dest->id);
        $this->postText($emoji);
        $this->postCallback('cp:hndconf:yes');
        $this->assertNoState();
    }

    /** Pure URL wizard (callback path): channel picker → channel → text → yes. */
    private function runPureUrlWizard(ChannelPosterDestination $dest, string $text): void
    {
        $this->postCallback('cp:pururl');
        $this->postCallback('cp:purch:' . $dest->id);
        $this->postText($text);
        $this->postCallback('cp:purconf:yes');
        $this->assertNoState();
    }

    /**
     * Smart-signature wizard (single tag):
     * channel → policy all_channels → layout multi_line (skips separator)
     * → apply always_apply → preview yes.
     */
    private function runSmartSigWizard(ChannelPosterDestination $dest): void
    {
        $this->postCallback('cp:ssig');
        $this->postCallback('cp:ssigc:' . $dest->id);
        $this->postCallback('cp:ssigpol:' . SignatureSettingsService::POLICY_ALL_CHANNELS);
        $this->postCallback('cp:ssiglay:' . SignatureSettingsService::LAYOUT_MULTI_LINE);
        $this->postCallback('cp:ssigap:' . SignatureSettingsService::APPLY_ALWAYS);
        $this->postCallback('cp:ssigpv:yes');
        $this->assertNoState();
    }

    /**
     * Post 'سلام', answer the legacy signature question when (and only when)
     * it is offered, then publish immediately.
     */
    private function publishNow(bool $expectLegacySigQuestion): void
    {
        $this->postText('سلام');
        $data = $this->lastPrivateCallbackData();
        if ($expectLegacySigQuestion) {
            $this->assertContains('cp:sig:yes', $data, 'legacy policy must offer the signature question');
            $this->postCallback('cp:sig:yes');
        } else {
            $this->assertNotContains('cp:sig:yes', $data, 'smart always_apply policy must not offer the legacy signature question');
        }
        $this->postCallback('cp:now');
        $this->assertNoState();
    }

    /**
     * LAST publish entry matching the platform (a test may publish twice).
     */
    private function publishByPlatform(string $platform): array
    {
        $found = null;
        foreach ($this->publisher->publishes as $pub) {
            if (($pub['platform'] ?? null) === $platform) {
                $found = $pub;
            }
        }
        $this->assertNotNull($found, 'no publish recorded for platform: ' . $platform);

        return $found;
    }

    // ─────────────────────────────────────────────────────────────────────
    // webhook / state helpers (copied from the signature suites)
    // ─────────────────────────────────────────────────────────────────────

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
