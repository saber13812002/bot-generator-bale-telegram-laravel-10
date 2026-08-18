<?php

namespace Tests\Feature;

use App\Console\Commands\GrowthDispatchDueCommand;
use App\Http\Controllers\GrowthCompanionController;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\GrowthProfile;
use App\Models\GrowthProfileTopic;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
use App\Models\GrowthQuestionSchedule;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;
use Tests\Fakes\FakeGrowthMessenger;
use Tests\Fakes\FakeGrowthMessengerFactory;
use Tests\TestCase;
use Tests\UsesGrowthCompanionSqlite;

class GrowthDispatchDueCommandTest extends TestCase
{
    use UsesGrowthCompanionSqlite;

    private FakeGrowthMessenger $messenger;

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
    }

    public function test_sends_due_question_and_advances_schedule(): void
    {
        $bot = Bot::create([
            'endpoint_id' => GrowthCompanionController::ENDPOINT_ID,
            'language_code' => 'fa',
            'bale_bot_token' => 'tok',
        ]);
        $user = BotUsers::create([
            'chat_id' => 777,
            'bot_id' => $bot->id,
            'origin' => 'bale',
            'status' => 'active',
        ]);
        $profile = GrowthProfile::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'timezone' => 'Asia/Tehran',
            'notify_time' => '21:00:00',
            'interaction_budget_per_day' => 1,
            'onboarding_completed_at' => now(),
        ]);
        $program = GrowthProgram::create([
            'growth_profile_id' => $profile->id,
            'bot_id' => $bot->id,
            'bot_user_id' => $user->id,
            'name' => 'سلامت',
            'status' => 'active',
        ]);
        $question = GrowthQuestion::create([
            'growth_program_id' => $program->id,
            'question_key' => 'daily_health_reflection',
            'frequency' => 'daily',
            'active' => true,
        ]);
        GrowthQuestionVariant::create([
            'growth_question_id' => $question->id,
            'body' => 'امروز برای سلامت چه کردی؟',
            'locale' => 'fa',
        ]);
        $schedule = GrowthQuestionSchedule::create([
            'growth_question_id' => $question->id,
            'cadence_type' => 'daily',
            'time_local' => '21:00:00',
            'next_due_at' => now()->subMinute(),
        ]);

        $this->artisan(GrowthDispatchDueCommand::class)->assertSuccessful();

        $this->assertStringContainsString('امروز برای سلامت چه کردی؟', (string) $this->messenger->lastText());
        $schedule->refresh();
        $this->assertNotNull($schedule->last_sent_at);
        $this->assertTrue($schedule->next_due_at->greaterThan(now()));
    }

    public function test_skips_paused_question(): void
    {
        $bot = Bot::create([
            'endpoint_id' => GrowthCompanionController::ENDPOINT_ID,
            'bale_bot_token' => 'tok',
        ]);
        $user = BotUsers::create([
            'chat_id' => 778,
            'bot_id' => $bot->id,
            'origin' => 'bale',
            'status' => 'active',
        ]);
        $profile = GrowthProfile::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'onboarding_completed_at' => now(),
        ]);
        $program = GrowthProgram::create([
            'growth_profile_id' => $profile->id,
            'bot_id' => $bot->id,
            'bot_user_id' => $user->id,
            'name' => 'سلامت',
            'status' => 'active',
        ]);
        $question = GrowthQuestion::create([
            'growth_program_id' => $program->id,
            'question_key' => 'daily_health_reflection',
            'frequency' => 'daily',
            'active' => true,
            'paused_at' => now(),
        ]);
        GrowthQuestionSchedule::create([
            'growth_question_id' => $question->id,
            'cadence_type' => 'daily',
            'next_due_at' => now()->subMinute(),
        ]);

        $this->artisan(GrowthDispatchDueCommand::class)->assertSuccessful();
        $this->assertSame([], $this->messenger->messages);
    }

    public function test_skips_when_already_answered_today(): void
    {
        $bot = Bot::create([
            'endpoint_id' => GrowthCompanionController::ENDPOINT_ID,
            'language_code' => 'fa',
            'bale_bot_token' => 'tok',
        ]);
        $user = BotUsers::create([
            'chat_id' => 779,
            'bot_id' => $bot->id,
            'origin' => 'bale',
            'status' => 'active',
        ]);
        $profile = GrowthProfile::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'timezone' => 'Asia/Tehran',
            'notify_time' => '21:00:00',
            'interaction_budget_per_day' => 2,
            'day_reset_hour' => 3,
            'onboarding_completed_at' => now(),
        ]);
        $program = GrowthProgram::create([
            'growth_profile_id' => $profile->id,
            'bot_id' => $bot->id,
            'bot_user_id' => $user->id,
            'name' => 'سلامت',
            'template_slug' => 'health',
            'status' => 'active',
        ]);
        GrowthProfileTopic::create([
            'growth_profile_id' => $profile->id,
            'template_slug' => 'health',
            'enabled' => true,
            'cadence' => 'daily',
            'sort_order' => 0,
        ]);
        $question = GrowthQuestion::create([
            'growth_program_id' => $program->id,
            'question_key' => 'daily_health_reflection',
            'frequency' => 'daily',
            'active' => true,
        ]);
        GrowthQuestionVariant::create([
            'growth_question_id' => $question->id,
            'body' => 'امروز برای سلامت چه کردی؟',
            'locale' => 'fa',
        ]);
        GrowthQuestionSchedule::create([
            'growth_question_id' => $question->id,
            'cadence_type' => 'daily',
            'next_due_at' => now()->subMinute(),
        ]);
        GrowthResponse::create([
            'growth_question_id' => $question->id,
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'body' => 'انجام شد',
            'answered_at' => now(),
        ]);

        $this->artisan(GrowthDispatchDueCommand::class)->assertSuccessful();
        $this->assertSame([], $this->messenger->messages);
    }
}
