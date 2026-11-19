<?php

namespace Tests\Feature;

use App\Http\Controllers\GrowthCompanionController;
use App\Interfaces\Services\GrowthLlmProvider;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\Bot;
use App\Models\GrowthDailyCheckin;
use App\Models\GrowthProfile;
use App\Models\GrowthProfileTopic;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;
use App\Models\GrowthReview;
use App\Models\GrowthTemplate;
use Carbon\Carbon;
use Tests\Fakes\FakeGrowthLlmProvider;
use Tests\Fakes\FakeGrowthMessenger;
use Tests\Fakes\FakeGrowthMessengerFactory;
use Tests\TestCase;
use Tests\UsesGrowthCompanionSqlite;

class GrowthCompanionBoardTest extends TestCase
{
    use UsesGrowthCompanionSqlite;

    private FakeGrowthMessenger $messenger;

    private string $token = '123:growth-token';

    private string $chatId = '555';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpGrowthCompanionTables();
        app()->setLocale('fa');

        $this->messenger = new FakeGrowthMessenger();
        $this->app->instance(
            GrowthMessengerFactory::class,
            new FakeGrowthMessengerFactory($this->messenger)
        );
        $this->app->instance(GrowthLlmProvider::class, new FakeGrowthLlmProvider());

        $this->seedHealthTemplate();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_onboarding_shows_home_card_not_topic_board(): void
    {
        $this->createBot();
        $this->completeOnboarding();

        $this->assertGreaterThanOrEqual(6, GrowthProgram::count());
        $this->assertTrue(GrowthProgram::where('template_slug', 'health')->exists());
        $this->assertStringContainsString(trans('growth_companion.home_title'), (string) $this->messenger->lastText());
        $this->assertStringContainsString(trans('growth_companion.home_no_checkin'), (string) $this->messenger->lastText());
        $this->assertNotContains('gc:b:health', $this->callbackDatas());
        $this->assertContains(trans('growth_companion.nav.today'), $this->replyTexts());
        $this->assertSame(0, $this->messenger->questionMessageCount());
        $this->assertSame(6, GrowthProfileTopic::where('enabled', true)->count());

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:topics'));
        $this->assertContains('gc:b:health', $this->callbackDatas());
        $this->assertStringContainsString(trans('growth_companion.topics_title'), (string) $this->messenger->lastText());
    }

    public function test_second_start_does_not_resend_question(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));

        $this->assertSame(0, $this->messenger->questionMessageCount());
        $this->assertStringContainsString(trans('growth_companion.home_title'), (string) $this->messenger->lastText());
    }

    public function test_question_screen_has_later_only(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:q'));

        $this->assertSame(1, $this->messenger->questionMessageCount());
        $this->assertContains('gc:later', $this->callbackDatas());
        $this->assertNotContains('gc:set', $this->callbackDatas());
        $this->assertCount(1, $this->callbackDatas());
    }

    public function test_answering_checks_topic_and_repeat_tap_does_not_resend(): void
    {
        $this->createBot();
        $this->completeOnboarding();

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->assertSame(1, $this->messenger->questionMessageCount());

        $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('امروز پیاده رفتم')
        );
        $this->assertSame(1, GrowthResponse::count());
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:topics'));
        $this->assertTrue($this->buttonHasPrefix('سلامت', '✅'));

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->assertSame(1, $this->messenger->questionMessageCount());
        $this->assertSame(trans('growth_companion.already_reviewed_today'), $this->messenger->lastCallbackText());
        $this->assertContains('gc:pro:m', $this->callbackDatas());
    }

    public function test_day_reset_clears_checkboxes_after_3am(): void
    {
        $this->createBot();
        Carbon::setTestNow(Carbon::parse('2026-08-18 04:00:00', 'Asia/Tehran'));
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('پاسخ روز اول'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:topics'));
        $this->assertTrue($this->buttonHasPrefix('سلامت', '✅'));

        Carbon::setTestNow(Carbon::parse('2026-08-19 02:00:00', 'Asia/Tehran'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:topics'));
        $this->assertTrue($this->buttonHasPrefix('سلامت', '✅'));

        Carbon::setTestNow(Carbon::parse('2026-08-19 04:00:00', 'Asia/Tehran'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:topics'));
        $this->assertTrue($this->buttonHasPrefix('سلامت', '☐'));
    }

    public function test_add_and_remove_category(): void
    {
        $this->createBot();
        $this->completeOnboarding();

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:add'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:a:sport'));
        $this->assertTrue(GrowthProfileTopic::where('template_slug', 'sport')->where('enabled', true)->exists());
        $this->assertContains('gc:b:sport', $this->callbackDatas());

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:x:sport'));
        $this->assertFalse(GrowthProfileTopic::where('template_slug', 'sport')->where('enabled', true)->exists());
        $this->assertNotContains('gc:b:sport', $this->callbackDatas());
        $this->assertGreaterThan(0, GrowthResponse::count() + GrowthProgram::count());
    }

    public function test_minimal_blocks_second_topic_active_allows_it(): void
    {
        $this->createBot();
        $this->completeOnboarding('gc:i:min');
        $this->assertSame(1, (int) GrowthProfile::first()->interaction_budget_per_day);

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('یک'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:family'));
        $this->assertSame(trans('growth_companion.budget_full'), $this->messenger->lastCallbackText());
        $this->assertSame(1, $this->messenger->questionMessageCount());

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:int:act'));
        $this->assertSame(2, (int) GrowthProfile::first()->interaction_budget_per_day);

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:family'));
        $this->assertSame(2, $this->messenger->questionMessageCount());
    }

    public function test_weekly_topic_stays_locked_until_week_boundary(): void
    {
        $this->createBot();
        Carbon::setTestNow(Carbon::parse('2026-08-18 12:00:00', 'Asia/Tehran'));
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:c:health'));
        $this->assertSame('weekly', GrowthProfileTopic::where('template_slug', 'health')->value('cadence'));

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('پاسخ هفتگی'));

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'Asia/Tehran'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->assertSame(trans('growth_companion.already_reviewed_week'), $this->messenger->lastCallbackText());
        $this->assertSame(1, $this->messenger->questionMessageCount());
    }

    public function test_weekly_review_and_export_and_ai_variants(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('جواب'));

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:rev'));
        $this->assertStringContainsString(trans('growth_companion.weekly_section_title'), (string) $this->messenger->lastText());
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:revw'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('هفته خوبی بود'));
        $this->assertSame(1, GrowthReview::count());

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:exp'));
        $this->assertStringContainsString('جواب', (string) $this->messenger->lastText());

        $this->grantPro();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:adv'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:aic'));
        $before = GrowthQuestionVariant::count();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:ai'));
        $this->assertGreaterThan($before, GrowthQuestionVariant::count());
    }

    public function test_weekday_toggle_in_advanced_mode(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:adv'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:k:health:1'));
        $topic = GrowthProfileTopic::where('template_slug', 'health')->first();
        $this->assertContains(1, $topic->weekdays ?? []);
    }

    public function test_four_step_checkin_feeds_home_card(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:in'));
        $this->assertStringContainsString(trans('growth_companion.checkin_mood'), (string) $this->messenger->lastText());
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:in:m:good'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:in:e:7'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:in:s:7'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:in:v:1'));

        $this->assertSame(1, GrowthDailyCheckin::count());
        $row = GrowthDailyCheckin::first();
        $this->assertSame('good', $row->mood);
        $this->assertSame(7, (int) $row->energy);
        $this->assertSame(7, (int) $row->sleep_hours);
        $this->assertTrue((bool) $row->moved);
        $this->assertStringContainsString(trans('growth_companion.home_title'), (string) $this->messenger->lastText());
        $this->assertStringContainsString(trans('growth_companion.home_energy', ['n' => 7]), (string) $this->messenger->lastText());
    }

    public function test_more_menu_has_export_and_privacy(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:more'));

        $this->assertStringContainsString(trans('growth_companion.more_title'), (string) $this->messenger->lastText());
        $this->assertContains('gc:exp', $this->callbackDatas());
        $this->assertContains('gc:priv', $this->callbackDatas());
        $this->assertContains('gc:topics', $this->callbackDatas());
        $this->assertContains('gc:hist', $this->callbackDatas());
        $this->assertContains('gc:pro', $this->callbackDatas());
    }

    public function test_budget_lock_shows_pro_and_pro_user_can_continue(): void
    {
        $this->createBot();
        $this->completeOnboarding('gc:i:min');
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('یک'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:family'));
        $this->assertSame(trans('growth_companion.budget_full'), $this->messenger->lastCallbackText());
        $this->assertContains('gc:pro:m', $this->callbackDatas());
        $this->assertSame(1, $this->messenger->questionMessageCount());

        $this->grantPro();

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:family'));
        $this->assertSame(2, $this->messenger->questionMessageCount());
    }

    public function test_pro_request_is_pending_until_confirm(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:pro:m'));
        $this->assertSame(1, \App\Models\ProPurchaseRequest::count());
        $this->assertSame('pending', \App\Models\ProPurchaseRequest::first()->status);
        $this->assertStringContainsString(trans('growth_companion.pro_requested', [
            'amount' => number_format((int) config('growth.pro.monthly_promo'), 0, '', '٬'),
            'admin' => (string) config('growth.pro.admin'),
            'card' => trans('growth_companion.pro_card_from_admin'),
        ]), (string) $this->messenger->lastText());
    }

    public function test_advanced_mode_requires_pro(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:adv'));
        $this->assertContains('gc:pro:m', $this->callbackDatas());
        $this->assertSame('simple', \App\Models\GrowthProfile::first()->mode);
    }

    private function grantPro(): void
    {
        $user = \App\Models\BotUsers::first();
        $bot = Bot::first();
        \App\Models\ProUser::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'status' => 'active',
            'purchase_requested_at' => now(),
            'purchase_confirmed_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function completeOnboarding(string $intensityCallback = 'gc:i:bal'): void
    {
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:f:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate($intensityCallback));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:t:skip'));
    }

    private function createBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => GrowthCompanionController::ENDPOINT_ID,
            'language_code' => 'fa',
            'bale_bot_token' => $this->token,
            'bot_mother_id' => 1,
        ]);
    }

    private function seedHealthTemplate(): void
    {
        $template = GrowthTemplate::create([
            'slug' => 'health',
            'name' => 'سلامت',
            'is_system' => true,
        ]);
        $template->questions()->create([
            'question_key' => 'daily_health_reflection',
            'intent' => 'daily_health_checkin',
            'domain' => 'health',
            'difficulty' => 1,
            'default_frequency' => 'daily',
            'variants' => [
                ['locale' => 'fa', 'body' => 'امروز برای سلامت چه کردی؟'],
                ['locale' => 'en', 'body' => 'What did you do for your health today?'],
            ],
        ]);
    }

    private function callbackDatas(): array
    {
        $datas = [];
        foreach ($this->messenger->lastKeyboard() ?? [] as $row) {
            foreach ($row as $button) {
                $datas[] = $button['callback_data'] ?? '';
            }
        }

        return $datas;
    }

    private function buttonHasPrefix(string $label, string $prefix): bool
    {
        foreach ($this->messenger->lastKeyboard() ?? [] as $row) {
            foreach ($row as $button) {
                $text = (string) ($button['text'] ?? '');
                if (str_contains($text, $label) && str_starts_with($text, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function replyTexts(): array
    {
        $texts = [];
        foreach ($this->messenger->lastReplyKeyboard() ?? [] as $row) {
            foreach ($row as $button) {
                $texts[] = $button['text'] ?? '';
            }
        }

        return $texts;
    }

    private function textUpdate(string $text): array
    {
        return [
            'update_id' => random_int(1, 99999),
            'message' => [
                'message_id' => random_int(1, 999),
                'from' => ['id' => (int) $this->chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $this->chatId, 'type' => 'private'],
                'date' => time(),
                'text' => $text,
            ],
        ];
    }

    private function callbackUpdate(string $data): array
    {
        return [
            'update_id' => random_int(1, 99999),
            'callback_query' => [
                'id' => 'cb'.random_int(1, 999),
                'from' => ['id' => (int) $this->chatId],
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => (int) $this->chatId, 'type' => 'private'],
                ],
                'data' => $data,
            ],
        ];
    }
}
