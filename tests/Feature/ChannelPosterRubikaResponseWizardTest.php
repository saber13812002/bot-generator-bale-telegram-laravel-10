<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelPosterBotController;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * ویزارد /add مقصد روبیکا — پیام‌های متمایز شکست (CP-RUBIKA-RESPONSE-FIX / M4+M6).
 *
 * ریشهٔ مشکل (اثبات‌شده از لاگ پروتکشن): پیام تست واقعا‌ً منتشر می‌شد، اما
 * parser پاکت دو سطحی {"status":...,"data":{...}} را نمی‌خواند و کاربر
 * همیشه پیام «ربات ادمین نیست» می‌گرفت و کانال ذخیره نمی‌شد.
 *
 * پوشش (همه MOCK_TESTED با Http::fake — هیچ درخواست زنده‌ای ارسال نمی‌شود):
 *  S1) پاکت واقعی (status+data با message_id تو‌در‌توی data) → destination
 *      ذخیره می‌شود + پیام وصل‌شدن، بدون یادداشت عدم‌تأیید
 *  S2) پذیرش صریح API بدون message_id → ذخیره می‌شود + پیام وصل‌شدن + یادداشت
 *      «شناسهٔ پیام نبود» (نه «ادمین نیست»)
 *  S3) 403 → پیام «ادمین نیست» (شواهد واقعی دسترسی) + state حفظ + ذخیره نشد
 *  S4) 401 → پیام «توکن نامعتبر» (نه «ادمین نیست»)
 *  S5) پاسخ 200 بدون مدرک موفقیت → متن دقیق «وضعیت ارسال تأیید نشد...» + state حفظ
 *  S6) خطای شبکه → پیام خطای شبکه + state حفظ
 *  S7) شکست و سپس تلاش دوباره با پاسخ موفق → destination ذخیره می‌شود
 */
class ChannelPosterRubikaResponseWizardTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const SOROUSH_TOKEN = '1234567:ABCDEFGHIJKLMNOPQRSTUVWXYZabcd1234';

    private const RUBIKA_TOKEN = 'rubikatoken123';

    private const CHANNEL_ID = '1234567';

    private string $ownerChatId = '111';

    /** متن‌هایی که ربات برای owner (از مبدأ soroush) ارسال می‌کند — برای بررسی پیام آخر. */
    private array $soroushTexts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
        // factory واقعی — هدف همین تست، کلاینت واقعی روبیکا + publisher native است
    }

    // ─────────────────────────────────────────────────────────────────────
    // S1) پاکت واقعی مشاهده‌شده در پروتکشن: message_id تو‌در‌توی data
    // ─────────────────────────────────────────────────────────────────────

    public function test_live_observed_envelope_saves_destination_without_unverified_note(): void
    {
        $bot = $this->createSoroushBot();

        $this->runWizardToChatId(self::CHANNEL_ID, Http::response([
            'status' => 'OK',
            'data' => ['message_id' => '777001'],
        ], 200));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . self::RUBIKA_TOKEN . '/sendMessage'
                && $request['chat_id'] === self::CHANNEL_ID
                && str_contains((string) $request['text'], trans('bot.channel_poster_test_message'));
        });

        $destination = ChannelPosterDestination::where('bot_id', $bot->id)
            ->where('platform', ChannelPosterDestination::PLATFORM_RUBIKA)
            ->first();
        $this->assertNotNull($destination);
        $this->assertSame(self::CHANNEL_ID, $destination->channel_chat_id);
        $this->assertSame('tag1', $destination->tag);
        $this->assertSame(self::RUBIKA_TOKEN, $destination->bot_token);
        $this->assertTrue((bool) $destination->is_active);

        $last = $this->lastSoroushText();
        $this->assertStringContainsString(
            trans('bot.channel_poster_rubika_connected', ['tag' => 'tag1']),
            $last
        );
        $this->assertStringNotContainsString(
            trans('bot.channel_poster_unverified_note'),
            $last
        );

        $this->assertNoState($bot->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // S2) پذیرش صریح API بدون message_id → موفقیت + یادداشت عدم‌تأیید
    // ─────────────────────────────────────────────────────────────────────

    public function test_explicit_success_without_message_id_saves_destination_with_unverified_note(): void
    {
        $bot = $this->createSoroushBot();

        $this->runWizardToChatId(self::CHANNEL_ID, Http::response([
            'status' => 'OK',
            'data' => [],
        ], 200));

        $destination = ChannelPosterDestination::where('bot_id', $bot->id)
            ->where('platform', ChannelPosterDestination::PLATFORM_RUBIKA)
            ->first();
        $this->assertNotNull($destination);
        $this->assertSame(self::CHANNEL_ID, $destination->channel_chat_id);

        $last = $this->lastSoroushText();
        $this->assertStringContainsString(
            trans('bot.channel_poster_rubika_connected', ['tag' => 'tag1']),
            $last
        );
        $this->assertStringContainsString(
            trans('bot.channel_poster_unverified_note'),
            $last
        );

        $this->assertNoState($bot->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // S3) 403 — شواهد واقعی عدم دسترسی → پیام «ادمین نیست» + state حفظ
    // ─────────────────────────────────────────────────────────────────────

    public function test_http_403_replies_not_admin_keeps_state_and_does_not_save(): void
    {
        $bot = $this->createSoroushBot();

        $this->runWizardToChatId(self::CHANNEL_ID, Http::response('{"error":"invalid"}', 403));

        $this->assertSame(0, ChannelPosterDestination::where('bot_id', $bot->id)->count());

        $last = $this->lastSoroushText();
        $this->assertStringContainsString(trans('bot.channel_poster_not_admin'), $last);

        $this->assertRubikaChatIdState($bot->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // S4) 401 — توکن نامعتبر است، نه «عدم دسترسی»
    // ─────────────────────────────────────────────────────────────────────

    public function test_http_401_replies_invalid_token_not_not_admin(): void
    {
        $bot = $this->createSoroushBot();

        $this->runWizardToChatId(self::CHANNEL_ID, Http::response('{"error":"invalid token"}', 401));

        $this->assertSame(0, ChannelPosterDestination::where('bot_id', $bot->id)->count());

        $last = $this->lastSoroushText();
        $this->assertStringContainsString(trans('bot.channel_poster_invalid_token'), $last);
        $this->assertStringNotContainsString(trans('bot.channel_poster_not_admin'), $last);

        $this->assertRubikaChatIdState($bot->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // S5) پاسخ 200 بدون هیچ مدرک موفقیت → متن دقیق نتیجهٔ نامشخص
    // ─────────────────────────────────────────────────────────────────────

    public function test_unknown_result_replies_exact_unknown_text_and_keeps_state(): void
    {
        $bot = $this->createSoroushBot();

        $this->runWizardToChatId(self::CHANNEL_ID, Http::response(['chat_id' => 'abc-chat-01'], 200));

        $this->assertSame(0, ChannelPosterDestination::where('bot_id', $bot->id)->count());

        // متن دقیق مأموریت M4 (زبان فارسی برنامه):
        $this->assertStringContainsString(
            'وضعیت ارسال تأیید نشد. قبل از تلاش مجدد، کانال را بررسی کنید تا پیام تکراری منتشر نشود.',
            $this->lastSoroushText()
        );

        $this->assertRubikaChatIdState($bot->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // S6) خطای شبکه/اتصال → پیام خطای شبکه + state حفظ
    // ─────────────────────────────────────────────────────────────────────

    public function test_network_error_replies_network_message_and_keeps_state(): void
    {
        $bot = $this->createSoroushBot();

        // stub هر-URL Closure: برای شبیه‌سازی خطای اتصال، مستقیم ConnectionException می‌اندازد
        // (Http::response(closure) در Laravel 10 Closure را اجرا نمی‌کند).
        $this->runWizardToChatId(self::CHANNEL_ID, function ($request) {
            throw new ConnectionException('connection timed out');
        });

        $this->assertSame(0, ChannelPosterDestination::where('bot_id', $bot->id)->count());

        $last = $this->lastSoroushText();
        $this->assertStringContainsString(trans('bot.channel_poster_test_send_network'), $last);
        $this->assertStringNotContainsString(trans('bot.channel_poster_not_admin'), $last);

        $this->assertRubikaChatIdState($bot->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // S7) شکست اولیه + تلاش دوباره با پاسخ موفق → ذخیره می‌شود
    // ─────────────────────────────────────────────────────────────────────

    public function test_failed_attempt_allows_retry_that_saves_destination(): void
    {
        $bot = $this->createSoroushBot();

        // Http::fake stubها را انباشته می‌کند (stub قبلی پاک نمی‌شود)؛ برای همین
        // کل تست — هر دو تلاش — با یک fake تنها اجرا می‌شود و stub روبیکا
        // پاسخ متفاوتی برای هر فراخوانی برمی‌گرداند.
        $rubikaCalls = 0;
        $this->fakeWizard(function ($request) use (&$rubikaCalls) {
            $rubikaCalls++;

            return $rubikaCalls === 1
                ? Http::response('{"error":"invalid"}', 403)
                : Http::response([
                    'status' => 'OK',
                    'data' => ['message_id' => '777002'],
                ], 200);
        });

        $this->postWizardSteps(self::CHANNEL_ID);
        $this->assertStringContainsString(
            trans('bot.channel_poster_not_admin'),
            $this->lastSoroushText()
        );
        $this->assertSame(0, ChannelPosterDestination::where('bot_id', $bot->id)->count());

        // API درست می‌شود؛ state حفظ شده بود → کاربر فقط شناسهٔ کانال را دوباره می‌فرستد
        $url = '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN;
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, self::CHANNEL_ID))->assertOk();

        $destination = ChannelPosterDestination::where('bot_id', $bot->id)
            ->where('platform', ChannelPosterDestination::PLATFORM_RUBIKA)
            ->first();
        $this->assertNotNull($destination);
        $this->assertSame(self::CHANNEL_ID, $destination->channel_chat_id);
        $this->assertStringContainsString(
            trans('bot.channel_poster_rubika_connected', ['tag' => 'tag1']),
            $this->lastSoroushText()
        );
        $this->assertNoState($bot->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * ویزارد را تا گام chat-id می‌راند و chat-id را می‌فرستد.
     *
     * $rubikaFake: stub مربوط به botapi.rubika.ir (در پاسخ تست مصرف می‌شود).
     */
    private function runWizardToChatId(string $chatId, object $rubikaFake): void
    {
        $this->fakeWizard($rubikaFake);
        $this->postWizardSteps($chatId);
    }

    private function fakeWizard(object $rubikaFake): void
    {
        // در Laravel 10، `Http::response(closure)` Closure را اجرا نمی‌کند؛
        // stub هر URL به‌صورت Closure جداگانه می‌آید (stubUrl Closure را صدا می‌زند).
        Http::fake($this->soroushRecordingFakes() + ['botapi.rubika.ir/*' => $rubikaFake]);
    }

    private function postWizardSteps(string $chatId): void
    {
        $url = '/api/webhook-channel-poster?origin=soroush&token=' . self::SOROUSH_TOKEN;
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, '/add'))->assertOk();
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, 'tag1'))->assertOk();
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, 'rubika'))->assertOk();
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, self::RUBIKA_TOKEN))->assertOk();
        $this->postJson($url, $this->soroushTextUpdate($this->ownerChatId, $chatId))->assertOk();
    }

    /** fake‌های مبدأ soroush — متن‌های ارسالی به owner را در $this->soroushTexts ثبت می‌کند. */
    private function soroushRecordingFakes(): array
    {
        return [
            'api.splus.ir/*' => function ($request) {
                $this->soroushTexts[] = (string) ($request['text'] ?? '');

                return Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200);
            },
        ];
    }

    private function lastSoroushText(): string
    {
        return $this->soroushTexts === [] ? '' : (string) end($this->soroushTexts);
    }

    private function assertRubikaChatIdState(int $botId): void
    {
        $botUser = BotUsers::where('bot_id', $botId)->first();
        $this->assertNotNull($botUser);
        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_RUBIKA_CHAT_ID, $state->state);
        $this->assertSame(self::RUBIKA_TOKEN, $state->getData('bot_token'));
        $this->assertSame('tag1', $state->getData('tag'));
    }

    private function assertNoState(int $botId): void
    {
        $botUser = BotUsers::where('bot_id', $botId)->first();
        $this->assertNotNull($botUser);
        $this->assertNull(BotUserState::where('bot_user_id', $botUser->id)->first());
    }

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
}
