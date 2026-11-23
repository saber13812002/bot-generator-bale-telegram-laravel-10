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
 * CP-CHANNEL-LINK: ثبت شناسهٔ عمومی کانال بله/روبیکا بدون تنظیم‌مجددِ کل کانال.
 *
 * Mandatory coverage (Phase 3):
 *   1. bale: /sethandle → wizard → public link → save, ALL other settings intact
 *   2. rubika (native origin, text-only): full wizard by typed numbers/words
 *   3. /channels: clear explanation + «🔗 ثبت شناسه کانال» button → direct link step
 *   4. /recheck: mixed status report, fix button only for missing, all-ok after fix
 *   5. wrong-platform link rejected, retry succeeds, nothing saved on reject
 *   6. existing channel_link is NOT overwritten; only filled when empty
 *   7. confirm «no» → no writes, state cleared
 *   8. /cancel mid-wizard → settings untouched
 *   9. multi-tag flow via typed numbers
 *   10. /settings menu exposes «ثبت شناسه کانال» + «بررسی مجدد تنظیمات»
 */
class ChannelPosterChannelLinkRegistrationTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const BALE_TOKEN = '123:channel-poster-token';

    private const RUBIKA_TOKEN = 'rubikatoken123';

    private FakeChannelPosterPublisher $publisher;

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
    // 1. Bale: full wizard via /sethandle, save + settings preservation
    // ------------------------------------------------------------------
    public function test_bale_full_wizard_saves_public_handle_and_preserves_settings(): void
    {
        $bot = $this->createBaleBot();
        $dest = $this->createBaleDest($bot->id);
        $before = $dest->refresh()->toArray();

        $this->postText('/sethandle');
        $this->assertStringContainsString(
            trans('bot.channel_chl_pick_channel'),
            $this->lastPrivateText(),
            'single-tag flow must go straight to the channel picker'
        );
        $this->assertContains('cp:chlch:' . $dest->id, $this->lastPrivateCallbackData());
        $this->assertState(ChannelPosterBotController::STATE_CHL_CHANNEL);
        // نشانگر وضعیت در دکمهٔ کانال: ❌ = بدون شناسهٔ عمومی
        $this->assertContains('در بله — stackoverflow_com ❌', $this->lastPrivateButtonTexts());

        $this->postCallback('cp:chlch:' . $dest->id);
        $this->assertSame(
            trans('bot.channel_chl_ask_link', [
                'channel' => 'stackoverflow_com',
                'platform' => trans('bot.channel_poster_sig_bale'),
            ]),
            $this->lastPrivateText(),
            'link step must explain what to send'
        );
        $this->assertState(ChannelPosterBotController::STATE_CHL_LINK);

        $this->postText('https://ble.ir/Saberblog');
        $this->assertStringContainsString('✅ شناسهٔ عمومی پیدا شد: @Saberblog', $this->lastPrivateText());
        $this->assertContains('cp:chlconf:yes', $this->lastPrivateCallbackData());
        $this->assertState(ChannelPosterBotController::STATE_CHL_CONFIRM);

        $this->postText('بله');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_chl_saved', ['channel' => 'stackoverflow_com', 'handle' => 'Saberblog']),
            $this->lastPrivateText()
        );

        $after = $dest->refresh()->toArray();
        $this->assertSame('Saberblog', $after['channel_username']);
        $this->assertSame('https://ble.ir/Saberblog', $after['channel_link']);

        // هیچ فیلد دیگری دست‌نخورده می‌ماند (حفظ تنظیمات قبلی)
        foreach (array_keys($before) as $col) {
            if (in_array($col, ['id', 'created_at', 'updated_at', 'channel_username', 'channel_link'], true)) {
                continue;
            }
            $this->assertSame($before[$col], $after[$col], "column {$col} must not change");
        }
    }

    // ------------------------------------------------------------------
    // 2. Rubika: native origin — text-only wizard (no keyboards)
    // ------------------------------------------------------------------
    public function test_rubika_native_flow_types_link_and_saves_preserving_settings(): void
    {
        $bot = $this->createRubikaBot();
        $dest = $this->createRubikaDest($bot->id);
        $before = $dest->refresh()->toArray();

        $this->postRubikaText('/sethandle');
        $this->assertStringContainsString(
            trans('bot.channel_chl_pick_channel'),
            $this->lastPrivateText()
        );
        $this->assertStringContainsString(
            trans('bot.channel_poster_platform_native_hint'),
            $this->lastPrivateText(),
            'native origin must explain that buttons are unavailable'
        );
        $this->assertStringContainsString(
            trans('bot.channel_chl_hint_tag'),
            $this->lastPrivateText(),
            'native origin must offer the typed-number fallback'
        );
        $this->assertState(ChannelPosterBotController::STATE_CHL_CHANNEL);

        $this->postRubikaText('1');
        $this->assertStringContainsString(trans('bot.channel_chl_hint_link'), $this->lastPrivateText());
        $this->assertStringContainsString('در روبیکا', $this->lastPrivateText());
        $this->assertState(ChannelPosterBotController::STATE_CHL_LINK);

        $this->postRubikaText('https://rubika.ir/SomeChannel');
        $this->assertStringContainsString('@SomeChannel', $this->lastPrivateText());
        $this->assertState(ChannelPosterBotController::STATE_CHL_CONFIRM);

        $this->postRubikaText('yes');
        $this->assertNoState();
        $this->assertStringContainsString(
            trans('bot.channel_chl_saved', ['channel' => 'Some Rubika Channel', 'handle' => 'SomeChannel']),
            $this->lastPrivateText()
        );

        $after = $dest->refresh()->toArray();
        $this->assertSame('SomeChannel', $after['channel_username']);
        $this->assertSame('https://rubika.ir/SomeChannel', $after['channel_link']);
        foreach (array_keys($before) as $col) {
            if (in_array($col, ['id', 'created_at', 'updated_at', 'channel_username', 'channel_link'], true)) {
                continue;
            }
            $this->assertSame($before[$col], $after[$col], "column {$col} must not change");
        }
    }

    // ------------------------------------------------------------------
    // 3. /channels: explanation + fix button → direct link step (no tag/channel)
    // ------------------------------------------------------------------
    public function test_channels_command_shows_explanation_and_direct_link_wizard(): void
    {
        $bot = $this->createBaleBot();
        $dest = $this->createBaleDest($bot->id);

        $this->postText('/channels');
        $text = $this->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_chl_missing_explain'), $text);
        $this->assertStringContainsString(trans('bot.channel_chl_missing_hint'), $text);
        $this->assertStringContainsString('❌ bale — stackoverflow_com (tag1)', $text);
        $this->assertContains('cp:unlink:' . $dest->id, $this->lastPrivateCallbackData());
        $this->assertContains('cp:chlfix:' . $dest->id, $this->lastPrivateCallbackData());

        $this->postCallback('cp:chlfix:' . $dest->id);
        $this->assertSame(
            trans('bot.channel_chl_ask_link', [
                'channel' => 'stackoverflow_com',
                'platform' => trans('bot.channel_poster_sig_bale'),
            ]),
            $this->lastPrivateText(),
            'fix button must go straight to the link step (no tag/channel step)'
        );
        $this->assertState(ChannelPosterBotController::STATE_CHL_LINK);

        $this->postText('https://ble.ir/Saberblog');
        $this->assertState(ChannelPosterBotController::STATE_CHL_CONFIRM);
        $this->postText('بله');
        $this->assertNoState();

        $this->assertSame('Saberblog', $dest->refresh()->channel_username);

        // /channels دیگر مشکل را نشان نمی‌دهد
        $this->postText('/channels');
        $this->assertStringNotContainsString(trans('bot.channel_chl_missing_explain'), $this->lastPrivateText());
        // نشانگر ✅ در متنِ دکمهٔ کانال (کیبورد) است، نه بدنهٔ پیام
        $this->assertContains('bale — stackoverflow_com (tag1) ✅', $this->lastPrivateButtonTexts());
    }

    // ------------------------------------------------------------------
    // 4. /recheck: mixed report → fix → all ok
    // ------------------------------------------------------------------
    public function test_recheck_reports_missing_then_all_ok_after_fix(): void
    {
        $bot = $this->createBaleBot();
        $baleDest = $this->createBaleDest($bot->id);
        $tgDest = $this->createDestination($bot->id, 'telegram', 'tag2', [
            'channel_chat_id' => 'saberblog',
            'channel_link' => 'https://t.me/saberblog/2784',
        ]);

        $this->postText('/recheck');
        $text = $this->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_recheck_header'), $text);
        $this->assertStringContainsString('❌ در بله — stackoverflow_com (tag1)', $text);
        $this->assertStringContainsString('✅ @saberblog — در تلگرام — Telegram Channel (tag2)', $text);
        $this->assertStringContainsString(trans('bot.channel_recheck_missing_note'), $text);
        // راهنمای «hint_fix» فقط برای مبدأهای native (سروش/روبیکا) به متن اضافه می‌شود
        // دکمهٔ رفع فقط برای کانالِ بلهٔ بی‌شناسه
        $this->assertSame(['cp:chlfix:' . $baleDest->id], $this->lastPrivateCallbackData());

        $this->postCallback('cp:chlfix:' . $baleDest->id);
        $this->postText('https://ble.ir/Saberblog');
        $this->postText('بله');
        $this->assertNoState();
        $this->assertSame('Saberblog', $baleDest->refresh()->channel_username);

        $this->postText('/recheck');
        $text = $this->lastPrivateText();
        $this->assertStringContainsString(trans('bot.channel_recheck_all_ok'), $text);
        $this->assertStringNotContainsString(trans('bot.channel_recheck_missing_note'), $text);
        $this->assertSame([], $this->lastPrivateCallbackData());
    }

    // ------------------------------------------------------------------
    // 5. Wrong-platform link rejected, retry succeeds, nothing saved early
    // ------------------------------------------------------------------
    public function test_wrong_platform_link_is_rejected_and_retry_succeeds(): void
    {
        $bot = $this->createBaleBot();
        $dest = $this->createBaleDest($bot->id);

        $this->postText('/sethandle');
        $this->postCallback('cp:chlch:' . $dest->id);
        $this->assertState(ChannelPosterBotController::STATE_CHL_LINK);

        $this->postText('https://rubika.ir/SomeChannel');
        $this->assertStringContainsString(
            trans('bot.channel_chl_wrong_platform', ['expected' => 'در بله', 'got' => 'در روبیکا']),
            $this->lastPrivateText()
        );
        $this->assertState(ChannelPosterBotController::STATE_CHL_LINK, 'must stay in the link step');
        $this->assertNull($dest->refresh()->channel_username);
        $this->assertNull($dest->refresh()->channel_link);

        $this->postText('https://ble.ir/Saberblog');
        $this->assertState(ChannelPosterBotController::STATE_CHL_CONFIRM);
        $this->postText('بله');
        $this->assertNoState();
        $this->assertSame('Saberblog', $dest->refresh()->channel_username);
    }

    // ------------------------------------------------------------------
    // 6. Existing channel_link is NOT overwritten (fill-only-if-empty)
    // ------------------------------------------------------------------
    public function test_existing_channel_link_is_not_overwritten(): void
    {
        $bot = $this->createBaleBot();
        $dest = $this->createBaleDest($bot->id, [
            'channel_link' => 'https://ble.ir/OldChannel/5062926176/1461',
        ]);

        $this->postText('/sethandle');
        $this->postCallback('cp:chlch:' . $dest->id);
        $this->postText('https://ble.ir/NewChannel');
        $this->postText('بله');
        $this->assertNoState();

        $after = $dest->refresh();
        $this->assertSame('NewChannel', $after->channel_username);
        $this->assertSame(
            'https://ble.ir/OldChannel/5062926176/1461',
            $after->channel_link,
            'existing channel_link must be kept (only channel_username is new)'
        );
    }

    // ------------------------------------------------------------------
    // 7. Confirm «no» → cancelled message, no writes
    // ------------------------------------------------------------------
    public function test_confirm_no_cancels_without_writing(): void
    {
        $bot = $this->createBaleBot();
        $dest = $this->createBaleDest($bot->id);

        $this->postText('/sethandle');
        $this->postCallback('cp:chlch:' . $dest->id);
        $this->postText('https://ble.ir/Saberblog');
        $this->assertState(ChannelPosterBotController::STATE_CHL_CONFIRM);

        $this->postText('نه');
        $this->assertNoState();
        $this->assertStringContainsString(trans('bot.channel_poster_hnd_cancelled'), $this->lastPrivateText());

        $after = $dest->refresh();
        $this->assertNull($after->channel_username);
        $this->assertNull($after->channel_link);
    }

    // ------------------------------------------------------------------
    // 8. /cancel mid-wizard → settings untouched
    // ------------------------------------------------------------------
    public function test_cancel_mid_wizard_keeps_previous_settings(): void
    {
        $bot = $this->createBaleBot();
        $dest = $this->createBaleDest($bot->id);
        $before = $dest->refresh()->toArray();

        $this->postText('/sethandle');
        $this->postCallback('cp:chlch:' . $dest->id);
        $this->assertState(ChannelPosterBotController::STATE_CHL_LINK);

        $this->postText('/cancel');
        $this->assertNoState();
        $this->assertStringContainsString(trans('bot.channel_poster_hnd_cancelled'), $this->lastPrivateText());

        $after = $dest->refresh()->toArray();
        foreach (array_keys($before) as $col) {
            if (in_array($col, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $this->assertSame($before[$col], $after[$col], "column {$col} must not change on cancel");
        }
    }

    // ------------------------------------------------------------------
    // 9. Multi-tag flow via typed numbers
    // ------------------------------------------------------------------
    public function test_multi_tag_flow_by_typed_numbers(): void
    {
        $bot = $this->createBaleBot();
        $destA = $this->createBaleDest($bot->id, [
            'tag' => 'tagA',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'First Channel',
        ]);
        $destB = $this->createBaleDest($bot->id, [
            'tag' => 'tagB',
            'channel_chat_id' => '8995754940207167598',
            'channel_title' => 'Second Channel',
        ]);

        $this->postText('/sethandle');
        $this->assertState(ChannelPosterBotController::STATE_CHL_TAG);
        $this->assertStringContainsString(trans('bot.channel_chl_ask_tag'), $this->lastPrivateText());
        // «hint_tag» فقط در مبدأهای native (سروش/روبیکا) به متن اضافه می‌شود
        $this->assertContains('cp:chltag:0', $this->lastPrivateCallbackData());
        $this->assertContains('cp:chltag:1', $this->lastPrivateCallbackData());

        // شمارهٔ نامعتبر → پیام خطا + ماندن در همان مرحله
        $this->postText('9');
        $this->assertStringContainsString(trans('bot.channel_chl_invalid_number'), $this->lastPrivateText());
        $this->assertState(ChannelPosterBotController::STATE_CHL_TAG);

        // انتخاب تگ دوم (tagB)
        $this->postText('2');
        $this->assertState(ChannelPosterBotController::STATE_CHL_CHANNEL);
        $this->assertContains('در بله — Second Channel ❌', $this->lastPrivateButtonTexts());
        $this->assertContains('cp:chlch:' . $destB->id, $this->lastPrivateCallbackData());

        // انتخاب کانال به‌صورت تایپ‌شده
        $this->postText('1');
        $this->assertState(ChannelPosterBotController::STATE_CHL_LINK);

        $this->postText('https://ble.ir/SecondChannel');
        $this->postText('بله');
        $this->assertNoState();

        $this->assertSame('SecondChannel', $destB->refresh()->channel_username);
        $this->assertNull($destA->refresh()->channel_username);
    }

    // ------------------------------------------------------------------
    // 10. /settings menu entries
    // ------------------------------------------------------------------
    public function test_settings_menu_shows_channel_link_and_recheck_entries(): void
    {
        $this->createBaleBot();
        $this->postText('/settings');

        $data = $this->lastPrivateCallbackData();
        $this->assertContains('cp:chlink', $data, 'owner menu must contain the channel-link entry');
        $this->assertContains('cp:chrecheck', $data, 'owner menu must contain the recheck entry');

        // دکمهٔ «بررسی مجدد» از منوی settings هم کار می‌کند
        $this->postCallback('cp:chrecheck');
        $this->assertStringContainsString(trans('bot.channel_chl_no_channels'), $this->lastPrivateText());
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function postText(string $text): void
    {
        $this->postJson('/api/webhook-channel-poster?origin=bale&token=' . self::BALE_TOKEN, [
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
        $this->postJson('/api/webhook-channel-poster?origin=bale&token=' . self::BALE_TOKEN, [
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

    /** روبیکا — envelope واقعی: { "update": { "type": "NewMessage", ... } } */
    private function postRubikaText(string $text): void
    {
        $this->postJson('/api/webhook-channel-poster?origin=rubika&token=' . self::RUBIKA_TOKEN, [
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => $this->ownerChatId,
                'new_message' => [
                    'message_id' => 10,
                    'text' => $text,
                    'sender_type' => 'User',
                    'sender_id' => $this->ownerChatId,
                ],
            ],
        ])->assertOk();
    }

    private function lastPrivateText(): ?string
    {
        return $this->publisher->lastPrivateText();
    }

    /** Flattened button `text`s of the last private-message keyboard. */
    private function lastPrivateButtonTexts(): array
    {
        return array_merge([], ...array_map(
            fn (array $row) => array_column($row, 'text'),
            $this->lastPrivateKeyboard()
        ));
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

    private function assertState(string $state): void
    {
        $bot = Bot::first();
        $user = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
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

    private function createBaleBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_token' => self::BALE_TOKEN,
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => (int) $this->ownerChatId,
            'bale_webhook_is_set' => true,
        ]);
    }

    private function createRubikaBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'rubika_bot_token' => self::RUBIKA_TOKEN,
            'rubika_bot_name' => 'channel_poster_rubika_bot',
            'rubika_bot_status' => 'Active',
            'rubika_owner_chat_id' => (int) $this->ownerChatId,
            'rubika_webhook_is_set' => true,
        ]);
    }

    /** مقصد بلهٔ forwardشده: chat id عددی + بدون شناسهٔ عمومی + تنظیمات قبلی. */
    private function createBaleDest(int $botId, array $attributes = []): ChannelPosterDestination
    {
        return ChannelPosterDestination::create(array_merge([
            'bot_id' => $botId,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'is_active' => true,
            'tag' => 'tag1',
            'signature_type' => 'inline',
            'signature_label' => 'امضای قدیمی',
            'signature_enabled' => true,
            'signature_settings' => ['separator' => '—', 'layout' => 'plain'],
            'bot_token' => 'dest-bale-token',
        ], $attributes));
    }

    /** مقصد روبیکا: chat id عددی + بدون شناسهٔ عمومی + تنظیمات قبلی. */
    private function createRubikaDest(int $botId, array $attributes = []): ChannelPosterDestination
    {
        return ChannelPosterDestination::create(array_merge([
            'bot_id' => $botId,
            'platform' => 'rubika',
            'channel_chat_id' => '1234567',
            'channel_title' => 'Some Rubika Channel',
            'is_active' => true,
            'tag' => 'tag1',
            'signature_type' => 'inline',
            'signature_label' => 'امضای قدیمی',
            'signature_enabled' => true,
            'signature_settings' => ['separator' => '—', 'layout' => 'plain'],
            'bot_token' => 'dest-rubika-token',
        ], $attributes));
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
