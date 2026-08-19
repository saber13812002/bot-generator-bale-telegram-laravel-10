<?php

namespace Tests\Feature;

use App\Http\Controllers\GrowthCompanionController;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\GrowthProfile;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
use App\Models\GrowthResponse;
use App\Models\GrowthTemplate;
use Tests\Fakes\FakeGrowthMessenger;
use Tests\Fakes\FakeGrowthMessengerFactory;
use Tests\TestCase;
use Tests\UsesGrowthCompanionSqlite;

class GrowthCompanionWebhookTest extends TestCase
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

        $this->seedHealthTemplate();
    }

    public function test_start_asks_focus(): void
    {
        $this->createBot();

        $response = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('/start')
        );

        $response->assertOk();
        $this->assertStringContainsString(trans('growth_companion.ask_focus'), (string) $this->messenger->lastText());
        $botUser = BotUsers::where('chat_id', $this->chatId)->first();
        $this->assertNotNull($botUser);
        $this->assertSame(
            GrowthCompanionController::STATE_FOCUS,
            BotUserState::where('bot_user_id', $botUser->id)->value('state')
        );
    }

    public function test_onboarding_creates_program_and_records_answer(): void
    {
        $bot = $this->createBot();
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:f:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:i:bal'));
        $response = $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:t:skip'));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(6, GrowthProgram::count());
        $this->assertSame('health', GrowthProgram::where('template_slug', 'health')->value('template_slug'));
        $this->assertStringContainsString(trans('growth_companion.home_title'), (string) $this->messenger->lastText());

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:b:health'));
        $answer = $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('امروز پیاده رفتم')
        );
        $answer->assertOk();
        $this->assertSame(1, GrowthResponse::count());
        $this->assertSame('امروز پیاده رفتم', GrowthResponse::first()->body);
        $this->assertStringContainsString(trans('growth_companion.ack'), (string) $this->messenger->lastText());
        $this->assertNotNull(GrowthProfile::where('bot_id', $bot->id)->first()->onboarding_completed_at);
    }

    public function test_pause_and_delete_data(): void
    {
        $this->createBot();
        $this->completeOnboarding();

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:p'));
        $this->assertNotNull(GrowthQuestion::first()->paused_at);

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:deld'));
        $this->assertSame(0, GrowthProfile::count());
        $this->assertSame(0, GrowthProgram::count());
        $this->assertStringContainsString(trans('growth_companion.data_deleted'), (string) $this->messenger->lastText());
    }

    public function test_custom_question(): void
    {
        $this->createBot();
        $this->completeOnboarding();

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:cq'));
        $this->postJson(
            '/api/webhook-growth-companion?origin=bale&token='.$this->token,
            $this->textUpdate('آیا امروز با خانواده وقت گذاشتم؟')
        );

        $custom = GrowthQuestion::where('source', 'user')->first();
        $this->assertNotNull($custom);
        $this->assertSame('آیا امروز با خانواده وقت گذاشتم؟', $custom->variants()->first()->body);
    }

    public function test_frequency_weekly(): void
    {
        $this->createBot();
        $this->completeOnboarding();

        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:freq:w'));
        $this->assertSame('weekly', GrowthQuestion::whereHas('program', function ($query) {
            $query->where('template_slug', 'health');
        })->value('frequency'));
    }

    private function completeOnboarding(): void
    {
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->textUpdate('/start'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:f:health'));
        $this->postJson('/api/webhook-growth-companion?origin=bale&token='.$this->token, $this->callbackUpdate('gc:i:bal'));
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
