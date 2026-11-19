<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelPosterBotController;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * Phase 6 — ویزارد /add کانال‌پست‌ر برای مبدأ‌های native (soroush/rubika)
 *
 * ریشه‌ی مشکل: NativeChannelPosterPublisher ردیف‌های کیبورد inline را انداخته
 * می‌شود (کیبورد در API هر دو پلتفرم مستند نیست — BLOCKED) و STATE_AWAITING_PLATFORM
 * فقط مسیر دکمه‌ای (cp:plat:X) داشت؛ نتیجه: ویزارد در انتخاب پلتفرم به بن‌بست می‌رسید.
 *
 * پوشش (همه MOCK_TESTED با Http::fake — LIVE_TESTED در Phase 7):
 *  1) مبدأ soroush: /add → تگ → 'rubika' (متن) → توکن → chat id؛
 *     پیام تست واقعاً به botapi.rubika.ir می‌رود و destination ذخیره می‌شود
 *  2) مبدأ rubika: /add → تگ → 'soroush' (متن) → توکن → chat id؛
 *     پیام تست واقعاً به api.splus.ir می‌رود و destination ذخیره می‌شود
 *  3) متن نامعتبر در STATE_AWAITING_PLATFORM: پیام خطا + state حفظ می‌شود
 *     (کاربر می‌تواند دوباره امتحان کند)
 *  4) رگرسیون مبدأ bale: مسیر متنی جدید در مبدأ‌های معمول هم کار می‌کند و
 *     برای مبدأ‌های غیر-native هیچ hint اضافه نمی‌شود (fake publisher چون
 *     کلاینت vendor با cURL صحبت می‌کند و Http::fake آن را نمی‌بیند)
 */
class ChannelPosterNativeAddWizardTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const SOROUSH_TOKEN = '1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcd1234';

    private const RUBIKA_TOKEN = 'rubikatoken123';

    private string $ownerChatId = '111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
        // factory واقعی — هدف همین تست، publisherهای native + کلاینت‌های واقعی است
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1) مبدأ soroush → مقصد rubika با ورودی متنی
    // ─────────────────────────────────────────────────────────────────────

    public function test_soroush_origin_add_wizard_adds_rubika_destination_via_text(): void
    {
        $bot = $this->createSoroushBot();

        Http::fake([
            'api.splus.ir/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200),
            'botapi.rubika.ir/*' => Http::response(['message_id' => '9001'], 200),
        ]);

        $url = '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN;
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, '/add'))->assertOk();

        // تگ (دستگاه STATE_AWAITING_TAG_NAME — ورودی متنی همیشه بوده)
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, 'tag1'))->assertOk();

        // پلتفرم با تایپ — مسیر جدید (دکمه در soroush ارسال نمی‌شود)
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, 'rubika'))->assertOk();

        // توکن ربات روبیکای مقصد
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, self::RUBIKA_TOKEN))->assertOk();

        // chat id کانال روبیکا → sendTestMessage از کلاینت واقعی (fake) + ذخیره
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, '1234567'))->assertOk();

        // پیام تست واقعاً به API روبیکا برای کانال مقصد رفته
        Http::assertSent(function ($request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . self::RUBIKA_TOKEN . '/sendMessage'
                && $request['chat_id'] === '1234567'
                && str_contains((string) $request['text'], trans('bot.channel_poster_test_message'));
        });

        $destination = ChannelPosterDestination::where('bot_id', $bot->id)
            ->where('platform', ChannelPosterDestination::PLATFORM_RUBIKA)
            ->first();
        $this->assertNotNull($destination);
        $this->assertSame('1234567', $destination->channel_chat_id);
        $this->assertSame('tag1', $destination->tag);
        $this->assertSame(self::RUBIKA_TOKEN, $destination->bot_token);
        $this->assertTrue((bool) $destination->is_active);

        // پیام وصل‌شدن به کاربر از مبدأ soroush (api.splus.ir)
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.splus.ir/bot' . self::SOROUSH_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_rubika_connected', ['tag' => 'tag1']));
        });

        // برای مبدأ native، hint «تایپ کنید» به پیام‌های ویزارد اضافه شده
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.splus.ir/bot' . self::SOROUSH_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_platform_native_hint'));
        });

        // state پاک شده
        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $this->assertNull(BotUserState::where('bot_user_id', $botUser->id)->first());
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2) مبدأ rubika → مقصد soroush با ورودی متنی
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_origin_add_wizard_adds_soroush_destination_via_text(): void
    {
        $bot = $this->createRubikaBot();

        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['message_id' => '9001'], 200),
            'api.splus.ir/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200),
        ]);

        $url = '/api/webhook-channel-poster?origin=rubika&token=' . self::RUBIKA_TOKEN;
        $this->postJson($url, $this->rubikaNewMessageUpdate($this->ownerChatId, '/add', 'User'))->assertOk();

        $this->postJson($url, $this->rubikaNewMessageUpdate($this->ownerChatId, 'tag1', 'User'))->assertOk();
        $this->postJson($url, $this->rubikaNewMessageUpdate($this->ownerChatId, 'soroush', 'User'))->assertOk();
        $this->postJson($url, $this->rubikaNewMessageUpdate($this->ownerChatId, self::SOROUSH_TOKEN, 'User'))->assertOk();
        $this->postJson($url, $this->rubikaNewMessageUpdate($this->ownerChatId, '777', 'User'))->assertOk();

        // پیام تست واقعاً به API سروش برای کانال مقصد رفته
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.splus.ir/bot' . self::SOROUSH_TOKEN . '/sendMessage'
                && $request['chat_id'] === '777'
                && str_contains((string) $request['text'], trans('bot.channel_poster_test_message'));
        });

        $destination = ChannelPosterDestination::where('bot_id', $bot->id)
            ->where('platform', ChannelPosterDestination::PLATFORM_SOROUSH)
            ->first();
        $this->assertNotNull($destination);
        $this->assertSame('777', $destination->channel_chat_id);
        $this->assertSame('tag1', $destination->tag);
        $this->assertSame(self::SOROUSH_TOKEN, $destination->bot_token);
        $this->assertTrue((bool) $destination->is_active);

        // پیام وصل‌شدن به کاربر از مبدأ rubika (botapi.rubika.ir)
        Http::assertSent(function ($request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . self::RUBIKA_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_soroush_connected', ['tag' => 'tag1']));
        });

        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $this->assertNull(BotUserState::where('bot_user_id', $botUser->id)->first());
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3) پلتفرم نامعتبر — خطا + state حفظ می‌شود (امکان تکرار)
    // ─────────────────────────────────────────────────────────────────────

    public function test_invalid_platform_text_keeps_state_and_allows_retry(): void
    {
        $bot = $this->createSoroushBot();

        Http::fake([
            'api.splus.ir/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200),
        ]);

        $url = '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN;
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, '/add'))->assertOk();
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, 'tag1'))->assertOk();
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, 'whatsapp'))->assertOk();

        // پیام خطای پلتفرم نامعتبر
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.splus.ir/bot' . self::SOROUSH_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_platform_invalid'));
        });

        // state همان cp_awaiting_platform با تگ حفظ شده
        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_PLATFORM, $state->state);
        $this->assertSame('tag1', $state->getData('tag'));

        // تلاش دوباره با پلتفرم معتبر کار می‌کند
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, 'soroush'))->assertOk();
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.splus.ir/bot' . self::SOROUSH_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_ask_soroush_token'));
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4) رگرسیون مبدأ bale — مسیر متنی در مبدأ‌های معمول + بدون hint
    // ─────────────────────────────────────────────────────────────────────

    public function test_bale_origin_add_wizard_text_path_works_without_hint(): void
    {
        $bot = $this->createBaleBot();

        $this->publisher = new FakeChannelPosterPublisher();
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );

        $url = '/api/webhook-channel-poster?origin=bale&token=' . $this->token;
        $this->postJson($url, $this->privateTextUpdate($this->ownerChatId, '/add'))->assertOk();
        $this->postJson($url, $this->privateTextUpdate($this->ownerChatId, 'tag1'))->assertOk();
        $this->postJson($url, $this->privateTextUpdate($this->ownerChatId, 'soroush'))->assertOk();

        // مسیر متنی در مبدأ bale هم به همان گام بعدی می‌رسد
        $this->assertStringContainsString(
            trans('bot.channel_poster_ask_soroush_token'),
            (string) $this->publisher->lastPrivateText()
        );

        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_SOROUSH_TOKEN, $state->state);

        // برای مبدأ غیر-native هیچ hint اضافه نشده
        foreach ($this->publisher->privateMessages as $message) {
            $this->assertStringNotContainsString(
                trans('bot.channel_poster_platform_native_hint'),
                $message['text']
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private FakeChannelPosterPublisher $publisher;

    private string $token = '123:channel-poster-token';

    private function createSoroushBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'soroush_bot_token' => self::SOROUSH_TOKEN,
            'soroush_bot_name' => 'channel_poster_soroush_bot',
            'soroush_bot_status' => 'Active',
            'soroush_owner_chat_id' => (int) $this->ownerChatId,
            'soroush_webhook_is_set' => true,
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

    /** soroush پلاس Telegram-style update می‌فرستد. */
    private function soroushTextUpdate(string $chatId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => (int) $chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'date' => time(),
                'text' => $text,
            ],
        ];
    }

    /** shape رسمی webhook روبیکا: {update:{type:NewMessage, chat_id, new_message:{...}}}. */
    private function rubikaNewMessageUpdate(string $chatId, string $text, string $senderType): array
    {
        return [
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => $chatId,
                'new_message' => [
                    'message_id' => 55,
                    'text' => $text,
                    'time' => time(),
                    'is_edited' => false,
                    'sender_type' => $senderType,
                    'sender_id' => $chatId,
                ],
            ],
        ];
    }

    private function privateTextUpdate(string $chatId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => (int) $chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'date' => time(),
                'text' => $text,
            ],
        ];
    }
}
