<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Http\Controllers\ChannelPosterBotController;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use App\Services\ChannelPoster\SignatureRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * Feature tests for Advanced Signature Management (الحاقیهٔ 1 مأموریت).
 *
 * Coverage of the 10 mandatory scenarios:
 *   1. wizard saves enabled signature (callback path)
 *   2. wizard saves disabled signature (خیر)
 *   3. wizard "بدون" label → null (platform default at render time)
 *   4. text confirm path (بله / نه / بازگشت / invalid)
 *   5. disabled signature renders empty (no corruption of main text)
 *   6. per-platform rendering via buildSignature (telegram html / bale md / rubika plain)
 *   7. legacy rows (signature_enabled=null) stay backward-compatible; explicit-false excluded
 *   8. caption length guard drops signature, never truncates main text
 *   9. queue retry never double-signs (success log skip)
 *  10. immediate publish via /cp:sig:yes + cp:now appends signature once
 */
class ChannelPosterSignatureManagementTest extends TestCase
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

    // ─────────────────────────── 1. wizard (callback) ───────────────────────────

    public function test_sign_wizard_saves_enabled_signature_end_to_end(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        // /sign → single tag → straight to channel picker
        $this->postText('/sign');
        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_pick_channel'),
            $this->publisher->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_CHANNEL);

        // pick the channel
        $this->postCallback('cp:signchn:' . $dest->id);
        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_no_link'),
            $this->publisher->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_LINK);

        // send the link (NOT persisted yet — deferred to confirm)
        $this->postText('https://t.me/ch');
        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_ask_label', [
                'link' => 'https://t.me/ch',
                'none' => trans('bot.channel_poster_sign_label_none'),
            ]),
            $this->publisher->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_LABEL);
        $this->assertNull($dest->refresh()->channel_link, 'link must not be persisted before confirm');

        // send the label
        $this->postText('کانال ما');
        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_ask_confirm', [
                'link' => 'https://t.me/ch',
                'label' => 'کانال ما',
            ]),
            $this->publisher->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_CONFIRM);

        // confirm yes
        $this->postCallback('cp:signconf:yes');

        $dest->refresh();
        $this->assertSame('https://t.me/ch', $dest->channel_link);
        $this->assertSame('کانال ما', $dest->signature_label);
        $this->assertSame('inline', $dest->signature_type);
        $this->assertTrue((bool) $dest->signature_enabled);
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_enabled', ['link' => 'https://t.me/ch']),
            $this->publisher->lastPrivateText()
        );
    }

    // ─────────────────────────── 2. wizard (خیر) ───────────────────────────

    public function test_sign_wizard_no_choice_saves_link_with_signature_disabled(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        $this->postText('/sign');
        $this->postCallback('cp:signchn:' . $dest->id);
        $this->postText('https://t.me/ch');
        $this->postText('کانال ما');
        $this->postCallback('cp:signconf:no');

        $dest->refresh();
        $this->assertSame('https://t.me/ch', $dest->channel_link);
        $this->assertSame('کانال ما', $dest->signature_label);
        $this->assertFalse((bool) $dest->signature_enabled);
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_disabled', ['link' => 'https://t.me/ch']),
            $this->publisher->lastPrivateText()
        );
    }

    // ─────────────────────────── 3. بدون label ───────────────────────────

    public function test_sign_wizard_label_badun_stores_null_label(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        $this->postText('/sign');
        $this->postCallback('cp:signchn:' . $dest->id);
        $this->postText('https://t.me/ch');
        $this->postText(trans('bot.channel_poster_sign_label_none'));
        $this->postCallback('cp:signconf:yes');

        $dest->refresh();
        $this->assertSame('https://t.me/ch', $dest->channel_link);
        $this->assertNull($dest->signature_label, 'بدون → null → platform default label at render time');
        $this->assertTrue((bool) $dest->signature_enabled);
    }

    public function test_sign_wizard_invalid_link_is_rejected_and_state_kept(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        $this->postText('/sign');
        $this->postCallback('cp:signchn:' . $dest->id);
        $this->postText('not-a-link');

        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_invalid_link'),
            $this->publisher->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_LINK);

        // retry with a valid link
        $this->postText('https://t.me/ch');
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_LABEL);
    }

    // ─────────────────────────── 4. text confirm path ───────────────────────────

    public function test_sign_confirm_text_yes_saves_enabled(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        $this->runWizardToConfirm($dest->id, 'https://t.me/ch', 'کانال ما');
        $this->postText('بله');

        $dest->refresh();
        $this->assertSame('https://t.me/ch', $dest->channel_link);
        $this->assertTrue((bool) $dest->signature_enabled);
        $this->assertNoState();
    }

    public function test_sign_confirm_text_neh_saves_disabled(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        $this->runWizardToConfirm($dest->id, 'https://t.me/ch', 'کانال ما');
        $this->postText('نه');

        $dest->refresh();
        $this->assertFalse((bool) $dest->signature_enabled);
        $this->assertNoState();
    }

    public function test_sign_confirm_text_bazgasht_returns_to_label_step_link_kept(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        $this->runWizardToConfirm($dest->id, 'https://t.me/ch', 'کانال ما');
        $this->postText('بازگشت');

        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_LABEL);
        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_ask_label', [
                'link' => 'https://t.me/ch',
                'none' => trans('bot.channel_poster_sign_label_none'),
            ]),
            $this->publisher->lastPrivateText()
        );
        $this->assertNull($dest->refresh()->channel_link, 'nothing persisted on back');

        // change the label, then confirm
        $this->postText('برچسب جدید');
        $this->postText('بله');
        $dest->refresh();
        $this->assertSame('برچسب جدید', $dest->signature_label);
        $this->assertSame('https://t.me/ch', $dest->channel_link);
    }

    public function test_sign_confirm_text_invalid_keeps_state(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1');

        $this->runWizardToConfirm($dest->id, 'https://t.me/ch', 'کانال ما');
        $this->postText('abc');

        $this->assertStringContainsString(
            trans('bot.channel_poster_sign_confirm_invalid'),
            $this->publisher->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_CONFIRM);
        $this->assertNull($dest->refresh()->channel_link);
    }

    // ─────────────────────────── 5. disabled → empty signature ───────────────────────────

    public function test_build_signature_returns_empty_when_disabled(): void
    {
        $bot = $this->createBot();
        $dest = $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
            'signature_enabled' => false,
        ]);

        $service = $this->app->make(ChannelPosterBotService::class);
        $sig = $service->buildSignature($bot->id, 'tag1', 'telegram');

        $this->assertSame(['text' => '', 'parse_mode' => null, 'button' => null], $sig);
    }

    public function test_build_signature_returns_empty_without_destination_for_platform(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            'signature_enabled' => true,
        ]);

        $service = $this->app->make(ChannelPosterBotService::class);
        $this->assertSame(
            ['text' => '', 'parse_mode' => null, 'button' => null],
            $service->buildSignature($bot->id, 'tag1', 'telegram')
        );
    }

    // ─────────────────────────── 6. per-platform rendering ───────────────────────────

    public function test_build_signature_renders_platform_specific_output(): void
    {
        $bot = $this->createBot();
        $link = 'https://t.me/ch';
        foreach (['telegram', 'bale', 'rubika'] as $platform) {
            $this->createDestination($bot->id, $platform, 'tag1', [
                'channel_link' => $link,
                'signature_label' => 'کانال ما',
                'signature_enabled' => true,
            ]);
        }

        $service = $this->app->make(ChannelPosterBotService::class);

        // telegram → HTML anchor
        $tg = $service->buildSignature($bot->id, 'tag1', 'telegram');
        $this->assertSame(
            "\n\nکانال ما:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $tg['text']
        );
        $this->assertSame('html', $tg['parse_mode']);

        // bale → Markdown link
        $bale = $service->buildSignature($bot->id, 'tag1', 'bale');
        $this->assertSame("\n\nکانال ما:\n[https://t.me/ch](https://t.me/ch)", $bale['text']);
        $this->assertNull($bale['parse_mode']);

        // rubika → safe plain fallback (no parse_mode in the API)
        $rubika = $service->buildSignature($bot->id, 'tag1', 'rubika');
        $this->assertSame("\n\nکانال ما:\nhttps://t.me/ch", $rubika['text']);
        $this->assertNull($rubika['parse_mode']);
    }

    // ─────────────────────────── 7. legacy backward compatibility ───────────────────────────

    public function test_legacy_row_with_null_enabled_renders_platform_default_label(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            // signature_label = null, signature_enabled = null → legacy behavior
        ]);

        $service = $this->app->make(ChannelPosterBotService::class);
        $sig = $service->buildSignature($bot->id, 'tag1', 'bale');

        // legacy: platform label from lang + [url](url) markdown
        $this->assertSame(
            "\n\n" . trans('bot.channel_poster_sig_bale') . ":\n[https://ble.ir/ch](https://ble.ir/ch)",
            $sig['text']
        );
        $this->assertNull($sig['parse_mode']);
    }

    public function test_legacy_no_platform_mode_renders_raw_lines_and_skips_explicit_false(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
        ]);
        $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
        ]);
        $disabled = $this->createDestination($bot->id, 'rubika', 'tag1', [
            'channel_link' => 'https://rubika.ir/ch',
            'signature_enabled' => false,
        ]);

        $service = $this->app->make(ChannelPosterBotService::class);
        $sig = $service->buildSignature($bot->id, 'tag1');

        // order is random (shuffle inside buildSignature) — compare as a set
        $lines = array_values(array_filter(explode("\n\n", $sig['text'])));
        sort($lines);
        $expected = [
            trans('bot.channel_poster_sig_bale') . ":\nhttps://ble.ir/ch",
            trans('bot.channel_poster_sig_telegram') . ":\nhttps://t.me/ch",
        ];
        sort($expected);
        $this->assertSame($expected, $lines);
        $this->assertNull($sig['parse_mode']);
        $this->assertNull($sig['button']);
        $this->assertStringNotContainsString('rubika.ir', $sig['text'], 'explicit-false destination must be excluded');
        $this->assertNotNull($disabled);
    }

    // ─────────────────────────── 8. caption guard ───────────────────────────

    public function test_queue_caption_guard_drops_signature_but_keeps_full_caption(): void
    {
        $bot = $this->createBot('123:queue-token');
        $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
            'signature_enabled' => true,
        ]);
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subHour(), [
            'content_type' => 'photo',
            'text' => str_repeat('x', 1020), // + signature > 1024
            'signature_enabled' => true,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, ?array $context) use ($item) {
                return $message === '[ChannelPoster] Signature dropped: caption would exceed limit'
                    && ($context['queue_id'] ?? null) === $item->id;
            });
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(
            str_repeat('x', 1020),
            $this->publisher->publishes[0]['text'],
            'main caption must be preserved exactly; signature is dropped, not truncated'
        );
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->refresh()->status);
    }

    public function test_queue_signature_appended_to_text_posts(): void
    {
        $bot = $this->createBot('123:queue-token');
        $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
            'signature_enabled' => true,
        ]);
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subHour(), [
            'signature_enabled' => true,
        ]);

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(
            'queued post text'
            . "\n\nکانال ما:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $this->publisher->publishes[0]['text']
        );
        $this->assertSame('html', $this->publisher->publishes[0]['parse_mode']);
        $this->assertNull($this->publisher->publishes[0]['reply_markup']);
    }

    // ─────────────────────────── 9. retry never double-signs ───────────────────────────

    public function test_queue_retry_publishes_only_failed_destination_without_double_signature(): void
    {
        $bot = $this->createBot('123:queue-token');
        $baleDest = $this->createDestination($bot->id, 'bale', 'tag1', [
            'channel_link' => 'https://ble.ir/ch',
            'signature_label' => 'کانال ما',
            'signature_enabled' => true,
        ]);
        $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
            'signature_enabled' => true,
        ]);
        $item = $this->createQueueItem($bot->id, 'tag1', now()->subHour(), [
            'signature_enabled' => true,
        ]);

        $this->publisher->failPlatforms = ['telegram'];

        // run 1: bale ok, telegram fails → item failed
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertFailed();
        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->refresh()->status);
        $this->assertCount(2, $this->publisher->publishes);
        // bale markdown link renders the URL twice ([url](url)) — count the closing half only
        $this->assertSame(1, $this->signatureCountIn($this->publisher->publishes[0]['text'] ?? '', '](https://ble.ir/ch)'));

        // retry: reset to pending and clear the platform failure so telegram succeeds
        $item->update(['status' => ChannelPosterQueue::STATUS_PENDING]);
        $this->publisher->failPlatforms = [];

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        // total publishes: 2 (run1) + 1 (run2: telegram only; bale skipped via success log)
        $this->assertCount(3, $this->publisher->publishes);
        $last = $this->publisher->publishes[2];
        $this->assertSame('telegram', $last['platform']);
        $this->assertSame(
            'queued post text'
            . "\n\nکانال ما:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $last['text'],
            'retry must append the signature exactly once — never double-sign'
        );
        // 't.me/ch' appears twice inside <a href="...">t.me/ch</a> — count the anchor itself
        $this->assertSame(1, $this->signatureCountIn($last['text'], 'a href="https://t.me/ch"'));
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->refresh()->status);
        $this->assertNotNull($baleDest);
    }

    // ─────────────────────────── 10. immediate publish via webhook ───────────────────────────

    public function test_immediate_publish_with_signature_yes_appends_signature_once(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
            'signature_enabled' => true,
        ]);

        // send content
        $this->postText('hello channel');
        $this->assertStringContainsString(
            trans('bot.channel_poster_ask_publish_options'),
            $this->publisher->lastPrivateText()
        );
        // signature rows are present because the channel has a link
        $this->assertSame(
            [
                [
                    ['text' => trans('bot.channel_poster_btn_sig_yes'), 'callback_data' => 'cp:sig:yes'],
                    ['text' => trans('bot.channel_poster_btn_sig_no'), 'callback_data' => 'cp:sig:no'],
                ],
            ],
            array_slice($this->publisher->privateMessages[0]['keyboard'] ?? [], 0, 1),
            'cp:sig:yes/no row must be offered when a channel link exists'
        );

        // opt in to signature, then publish now
        $this->postCallback('cp:sig:yes');
        $this->postCallback('cp:now');

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame(
            'hello channel'
            . "\n\nکانال ما:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $this->publisher->publishes[0]['text']
        );
        $this->assertSame('html', $this->publisher->publishes[0]['parse_mode']);
        $this->assertNull($this->publisher->publishes[0]['reply_markup']);
        // 't.me/ch' appears twice inside <a href="...">t.me/ch</a> — count the anchor itself
        $this->assertSame(1, $this->signatureCountIn($this->publisher->publishes[0]['text'], 'a href="https://t.me/ch"'));
    }

    public function test_immediate_publish_without_signature_sends_plain_text(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'telegram', 'tag1', [
            'channel_link' => 'https://t.me/ch',
            'signature_label' => 'کانال ما',
            'signature_enabled' => true,
        ]);

        $this->postText('hello channel');
        $this->postCallback('cp:sig:no');
        $this->postCallback('cp:now');

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('hello channel', $this->publisher->publishes[0]['text']);
        $this->assertNull($this->publisher->publishes[0]['parse_mode']);
    }

    public function test_immediate_publish_signature_no_rows_offered_when_no_channel_link(): void
    {
        $bot = $this->createBot();
        $this->createDestination($bot->id, 'telegram', 'tag1'); // no channel_link

        $this->postText('hello channel');
        $keyboard = $this->publisher->privateMessages[0]['keyboard'] ?? [];

        // only the now/queue row — no cp:sig rows
        $this->assertCount(1, $keyboard);
        $this->assertSame(
            [['text' => $keyboard[0][0]['text'], 'callback_data' => 'cp:now'], ['text' => $keyboard[0][1]['text'], 'callback_data' => 'cp:queue']],
            $keyboard[0]
        );

        $this->postCallback('cp:now');
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('hello channel', $this->publisher->publishes[0]['text']);
    }

    // ─────────────────────────── helpers ───────────────────────────

    private function runWizardToConfirm(int $destId, string $link, string $label): void
    {
        $this->postText('/sign');
        $this->postCallback('cp:signchn:' . $destId);
        $this->postText($link);
        $this->postText($label);
        $this->assertState(ChannelPosterBotController::STATE_AWAITING_SIGN_CONFIRM);
    }

    private function postText(string $text): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token=' . $this->token,
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

    private function postCallback(string $data): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token=' . $this->token,
            [
                'update_id' => 5,
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
        $botUser = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $this->assertSame(
            $state,
            BotUserState::where('bot_user_id', $botUser->id)->value('state'),
            'expected state: ' . $state
        );
    }

    private function assertNoState(): void
    {
        $bot = Bot::first();
        $botUser = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
        $this->assertNull(
            BotUserState::where('bot_user_id', $botUser->id)->value('state'),
            'state must be cleared'
        );
    }

    private function createBot(?string $token = null): Bot
    {
        $token = $token ?? $this->token;

        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_token' => $token,
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => (int) $this->ownerChatId,
            'bale_webhook_is_set' => true,
        ]);
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
            'tag' => $tag,
            'is_active' => true,
        ], $attributes));
    }

    private function createQueueItem(
        int $botId,
        string $tag,
        Carbon $scheduledAt,
        array $attributes = []
    ): ChannelPosterQueue {
        return ChannelPosterQueue::create(array_merge([
            'bot_id' => $botId,
            'tag' => $tag,
            'content_type' => 'text',
            'text' => 'queued post text',
            'scheduled_at' => $scheduledAt,
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_chat_id' => '111',
            'owner_origin' => 'bale',
        ], $attributes));
    }

    /**
     * Count how many times the signature link appears in the published text.
     */
    private function signatureCountIn(?string $text, string $needle): int
    {
        return $text === null ? 0 : substr_count($text, $needle);
    }
}
