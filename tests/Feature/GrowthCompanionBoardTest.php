<?php

namespace Tests\Feature;

use App\Http\Controllers\GrowthCompanionController;
use App\Interfaces\Services\GrowthLlmProvider;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\Bot;
use App\Models\GrowthProfile;
use App\Models\GrowthProfileTopic;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
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

    public function test_onboarding_shows_board_not_a_question(): void
    {
        $this->createBot();
        $this->completeOnboarding();

        $this->assertGreaterThanOrEqual(6, GrowthProgram::count());
        $this->assertTrue(GrowthProgram::where('template_slug', 'health')->exists());
        $this->assertStringContainsString(trans('growth_companion.board_title'), (string) $this->messenger->lastText());
        $this->assertContains('gc:b:health', $this->callbackDatas());
        $this->assertSame(0, $this->messenger->questionMessageCount());
        $this->assertSame(6, GrowthProfileTopic::where('enabled', true)->count());
    }

    public function test_second_start_does_not_resend_question(): void
    {
        $this->createBot();
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));

        $this->assertSame(0, $this->messenger->questionMessageCount());
        $this->assertStringContainsString(trans('growth_companion.board_title'), (string) $this->messenger->lastText());
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
        $this->assertTrue($this->buttonHasPrefix('سلامت', '✅'));

        $before = count($this->messenger->messages);
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->assertSame(1, $this->messenger->questionMessageCount());
        $this->assertSame(trans('growth_companion.already_reviewed_today'), $this->messenger->lastCallbackText());
        $this->assertSame($before, count($this->messenger->messages));
    }

    public function test_day_reset_clears_checkboxes_after_3am(): void
    {
        $this->createBot();
        Carbon::setTestNow(Carbon::parse('2026-08-18 04:00:00', 'Asia/Tehran'));
        $this->completeOnboarding();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('پاسخ روز اول'));
        $this->assertTrue($this->buttonHasPrefix('سلامت', '✅'));

        Carbon::setTestNow(Carbon::parse('2026-08-19 02:00:00', 'Asia/Tehran'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->assertTrue($this->buttonHasPrefix('سلامت', '✅'));

        Carbon::setTestNow(Carbon::parse('2026-08-19 04:00:00', 'Asia/Tehran'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
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
        $this->assertStringContainsString(trans('growth_companion.weekly_review_title'), (string) $this->messenger->lastText());
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:revw'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('هفته خوبی بود'));
        $this->assertSame(1, GrowthReview::count());

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:exp'));
        $this->assertStringContainsString('جواب', (string) $this->messenger->lastText());

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
