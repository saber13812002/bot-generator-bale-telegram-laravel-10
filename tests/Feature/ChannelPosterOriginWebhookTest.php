<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelPosterBotController;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use App\Services\ChannelPosterMediaStore;
use App\Services\NativeChannelPosterPublisher;
use App\Services\SyncPlatformRegistry;
use App\Services\TelegramChannelPosterPublisher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * Phase 5 — تعمیم‌شدن موتور کانال‌پست‌ر: soroush/rubika به‌عنوان «مبدأ» (origin)
 *
 * پوشش (همه MOCK_TESTED با Http::fake — LIVE_TESTED در Phase 7):
 *  1) /start از مبدأ soroush: ربات از ستون soroush_bot_token حل می‌شود و
 *     پاسخ با کلاینت داخلی به api.splus.ir ارسال می‌شود
 *  2) /start از مبدأ rubika: shape {update:{NewMessage,User}} نرمال‌سازی
 *     می‌شود و پاسخ با کلاینت داخلی به botapi.rubika.ir ارسال می‌شود
 *  3) update روبیکا با sender_type=Bot نادیده گرفته می‌شود
 *  4) update روبیکا با type غیر-NewMessage نادیده گرفته می‌شود
 *  5) رسانه از مبدأ soroush: دانلود از API مبدأ صورت نمی‌گیرد (data-driven
 *     skip؛ getFile تأییدنشده است) و جریان با file_id ادامه می‌یابد
 *  6) رسانه از مبدأ rubika: دانلود از API مبدأ صورت نمی‌گیرد (data-driven
 *     skip)؛ ساختار رسانه ورودی روبیکا (فیلد file) مستند نیست (BLOCKED)
 *     و موتور نباید شکل آن را حدس بزند — جریان با همان متن ادامه می‌یابد
 *  7) factory بر اساس config/sync_platforms.php publisher درست را برمی‌گرداند
 *     (vendor برای bale/telegram، native برای soroush/rubika)
 */
class ChannelPosterOriginWebhookTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const SOROUSH_TOKEN = '1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcd1234';

    private const RUBIKA_TOKEN = 'rubikatoken123';

    private string $ownerChatId = '111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
        // factory واقعی — هدف همین تست، رفتار factory/registry است
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1) /start از مبدأ soroush
    // ─────────────────────────────────────────────────────────────────────

    public function test_soroush_start_resolves_bot_and_replies_via_splus_api(): void
    {
        $bot = $this->createSoroushBot();

        Http::fake([
            'api.splus.ir/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200),
        ]);

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN,
            $this->soroushTextUpdate($this->ownerChatId, '/start')
        );

        $response->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.splus.ir/bot' . self::SOROUSH_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_welcome'))
                && str_contains((string) $request['text'], trans('bot.channel_poster_ask_bale_forward'));
        });

        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $this->assertSame('soroush', $botUser->origin);

        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_BALE_FORWARD, $state->state);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2) /start از مبدأ rubika (نرمال‌سازی shape روبیکا)
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_new_message_user_is_normalized_and_replies_via_rubika_api(): void
    {
        $bot = $this->createRubikaBot();

        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['message_id' => '9001'], 200),
        ]);

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=rubika&token=' . self::RUBIKA_TOKEN,
            $this->rubikaNewMessageUpdate($this->ownerChatId, '/start', 'User')
        );

        $response->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . self::RUBIKA_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_welcome'));
        });

        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $this->assertSame('rubika', $botUser->origin);

        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_BALE_FORWARD, $state->state);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3) پیام خودِ ربات در روبیکا نادیده گرفته می‌شود
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_new_message_from_bot_is_ignored(): void
    {
        $this->createRubikaBot();

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=rubika&token=' . self::RUBIKA_TOKEN,
            $this->rubikaNewMessageUpdate($this->ownerChatId, '/start', 'Bot')
        );

        $response->assertOk();
        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4) type غیر-NewMessage در روبیکا نادیده گرفته می‌شود
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_non_new_message_update_is_ignored(): void
    {
        $this->createRubikaBot();

        $payload = [
            'update' => [
                'type' => 'StartedBot',
                'chat_id' => $this->ownerChatId,
            ],
        ];

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=rubika&token=' . self::RUBIKA_TOKEN,
            $payload
        );

        $response->assertOk();
        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5) رسانه از مبدأ soroush — دانلود از مبدأ نمی‌شود (data-driven skip)
    // ─────────────────────────────────────────────────────────────────────

    public function test_soroush_origin_media_does_not_download_from_origin(): void
    {
        $bot = $this->createSoroushBot();
        $this->createDestination($bot);
        $spy = $this->bindMediaStoreSpy();

        Http::fake([
            'api.splus.ir/*' => Http::response(['ok' => true, 'result' => ['message_id' => 78]], 200),
        ]);

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN,
            $this->soroushPhotoUpdate($this->ownerChatId, 'soroush-photo-file-id', 'سلام')
        );

        $response->assertOk();

        // دانلود رسانه از API مبدأ اصلاً رخ نمی‌دهد (getFile تأییدنشده — BLOCKED)
        $this->assertSame(0, $spy->fetchCalls, 'fetchAndStore باید برای مبدأ soroush صدا زده نشود');

        // جریان ادامه دارد: پیام گزینه‌های انتشار با کلاینت داخلی ارسال می‌شود
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.splus.ir/bot' . self::SOROUSH_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_ask_publish_options'));
        });

        // محتوای معلق با همان file_id (و media_path خالی) ذخیره می‌شود
        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_DESTINATION, $state->state);
        $this->assertSame('soroush-photo-file-id', $state->data['file_id'] ?? null);
        $this->assertNull($state->data['media_path'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6) رسانه از مبدأ rubika — skip + ساختار رسانه ورودی unverified (BLOCKED)
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_origin_media_does_not_download_from_origin(): void
    {
        // registry باید برای مبدأ rubika دانلود از مبدأ را ممنوع کند
        $this->assertFalse(
            SyncPlatformRegistry::canDownloadMediaFromOrigin('rubika'),
            'registry باید برای مبدأ rubika دانلود از مبدأ را ممنوع کند'
        );

        $bot = $this->createRubikaBot();
        $this->createDestination($bot);
        $spy = $this->bindMediaStoreSpy();

        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['message_id' => '9002'], 200),
        ]);

        // فیلد رسانه ورودی (file) در مدل Message روبیکا مستند است ولی
        // ساختار درونی آن مستند نیست (BLOCKED (unverified)) — موتور نباید
        // شکل آن را حدس بزند؛ فقط text استخراج می‌شود و جریان ادامه می‌یابد.
        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=rubika&token=' . self::RUBIKA_TOKEN,
            $this->rubikaNewMessageUpdate($this->ownerChatId, 'سلام', 'User', [
                'file' => ['file_id' => 'rubika-file-id', 'file_type' => 'photo'],
            ])
        );

        $response->assertOk();

        // دانلود رسانه از API مبدأ اصلاً رخ نمی‌دهد (data-driven skip)
        $this->assertSame(0, $spy->fetchCalls, 'fetchAndStore باید برای مبدأ rubika صدا زده نشود');

        // جریان متن با کلاینت داخلی ادامه می‌یابد
        Http::assertSent(function ($request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . self::RUBIKA_TOKEN . '/sendMessage'
                && $request['chat_id'] === $this->ownerChatId
                && str_contains((string) $request['text'], trans('bot.channel_poster_ask_publish_options'));
        });

        $botUser = BotUsers::where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_DESTINATION, $state->state);
        // رسانه به‌دلیل ساختار unverified استخراج نمی‌شود → محتوا به‌صورت text
        $this->assertSame('text', $state->data['content_type'] ?? null);
        $this->assertNull($state->data['file_id'] ?? null);
        $this->assertNull($state->data['media_path'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7) factory config-driven (regression)
    // ─────────────────────────────────────────────────────────────────────

    public function test_factory_returns_config_driven_publisher_types(): void
    {
        $factory = $this->app->make(ChannelPosterPublisherFactory::class);

        $this->assertInstanceOf(TelegramChannelPosterPublisher::class, $factory->make('123:bale-token', 'bale'));
        $this->assertInstanceOf(TelegramChannelPosterPublisher::class, $factory->make('123:tg-token', 'telegram'));
        $this->assertInstanceOf(NativeChannelPosterPublisher::class, $factory->make(self::SOROUSH_TOKEN, 'soroush'));
        $this->assertInstanceOf(NativeChannelPosterPublisher::class, $factory->make(self::RUBIKA_TOKEN, 'rubika'));
        // origin ناشناخته: fall back به رفتار موروثی vendor
        $this->assertInstanceOf(TelegramChannelPosterPublisher::class, $factory->make('123:gap-token', 'gap'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

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

    private function createDestination(Bot $bot): ChannelPosterDestination
    {
        return ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => ChannelPosterDestination::PLATFORM_BALE,
            'channel_chat_id' => '999',
            'channel_title' => 'Test Channel',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
    }

    /**
     * جایگزین spy برای ChannelPosterMediaStore — صدازدن‌های fetchAndStore
     * را شمارش می‌کند و هیچ‌وقت فایل نمی‌سازد.
     */
    private function bindMediaStoreSpy(): ChannelPosterMediaStore
    {
        $spy = new class extends ChannelPosterMediaStore {
            public int $fetchCalls = 0;

            public function fetchAndStore(
                string $token,
                string $fileId,
                string $origin,
                string $mediaType,
                ?string $fileName = null
            ): ?string {
                $this->fetchCalls++;

                return null;
            }
        };

        $this->app->instance(ChannelPosterMediaStore::class, $spy);

        return $spy;
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

    private function soroushPhotoUpdate(string $chatId, string $fileId, string $caption): array
    {
        return [
            'update_id' => 2,
            'message' => [
                'message_id' => 11,
                'from' => ['id' => (int) $chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'date' => time(),
                'caption' => $caption,
                'photo' => [[
                    'file_id' => $fileId,
                    'file_unique_id' => 'u1',
                    'width' => 100,
                    'height' => 100,
                ]],
            ],
        ];
    }

    /**
     * shape رسمی webhook روبیکا:
     * { "update": { "type": "NewMessage", "chat_id", "new_message": {...} } }
     *
     * @param array<string, mixed> $extraMessageFields فیلدهای اختیاری new_message (مثلا photo)
     */
    private function rubikaNewMessageUpdate(string $chatId, string $text, string $senderType, array $extraMessageFields = []): array
    {
        $newMessage = array_merge([
            'message_id' => 55,
            'text' => $text,
            'time' => time(),
            'is_edited' => false,
            'sender_type' => $senderType,
            'sender_id' => $chatId,
        ], $extraMessageFields);

        return [
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => $chatId,
                'new_message' => $newMessage,
            ],
        ];
    }
}
