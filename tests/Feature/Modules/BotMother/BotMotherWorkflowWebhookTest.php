<?php

namespace Tests\Feature\Modules\BotMother;

use App\Helpers\BotMotherStateHelper;
use App\Helpers\WebhookMockHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression tests: the v2 bot mother webhook
 * (/api/webhook-bot-mother-v2) must delegate every non-workflow update
 * (legacy slash commands, unknown text, legacy callbacks) to the legacy
 * BotMotherController instead of replying "دستور نامعتبر" and dropping it.
 *
 * The mock chat id 485750575 is the super admin
 * (SUPER_ADMIN_CHAT_ID_BALE / CHAT_ID_ACCOUNT_1_SABER), so both the v2
 * controller and the legacy controller pass their admin checks.
 *
 * Note: the SDK's sendAPIRequest never throws on network failures, so the
 * real (unreachable/in-test) bot API endpoints are harmless in tests.
 */
class BotMotherWorkflowWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/webhook-bot-mother-v2?origin=bale&token=test_token&bot_mother_id=1';

    private const CHAT_ID = 485750575;

    protected function setUp(): void
    {
        parent::setUp();

        // The admin check reads env() directly; make sure the mock chat is
        // always an admin and the bot token resolves in the test environment.
        foreach ([
            'SUPER_ADMIN_CHAT_ID_BALE' => (string) self::CHAT_ID,
            'CHAT_ID_ACCOUNT_1_SABER' => (string) self::CHAT_ID,
            'BOT_MOTHER_TOKEN_BALE' => 'test_token',
        ] as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        Cache::flush();
        BotMotherStateHelper::clearState(self::CHAT_ID);
    }

    /**
     * A legacy slash command (/help) arriving at the v2 webhook is
     * delegated to BotMotherController, which logs it and answers with its
     * own help message — instead of the v2 "invalid command" reply.
     */
    public function test_slash_command_is_delegated_to_legacy_controller(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('/help');

        $response = $this->postJson(self::URL, $update);

        $response->assertStatus(200);

        // The legacy controller logged the message (LogHelper::log), which
        // only happens inside BotMotherController::botMotherWebhook.
        $this->assertDatabaseCount('bot_logs', 1);
        $this->assertDatabaseHas('bot_logs', ['text' => '/help']);

        // The v2 controller itself never creates a workflow state for it.
        $this->assertNull(BotMotherStateHelper::getCurrentState(self::CHAT_ID));
    }

    /**
     * Unknown plain text arriving at the v2 webhook is delegated to the
     * legacy controller, which logs it and answers with its own
     * "invalid command" help text.
     */
    public function test_unknown_plain_text_is_delegated_to_legacy_controller(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('some random text that is not a command');

        $response = $this->postJson(self::URL, $update);

        $response->assertStatus(200);

        // Delegated: the legacy controller logged the message.
        $this->assertDatabaseHas('bot_logs', [
            'text' => 'some random text that is not a command',
        ]);
    }

    /**
     * A legacy callback (language selection, lang_en) is delegated to the
     * legacy controller, which answers it and advances the wizard state to
     * "waiting for token" with the selected language.
     */
    public function test_legacy_language_callback_is_delegated_to_legacy_controller(): void
    {
        BotMotherStateHelper::setState(
            self::CHAT_ID,
            BotMotherStateHelper::STATE_WAITING_LANGUAGE,
            ['endpoint' => ['id' => 'weather-bot', 'requires_language' => true]]
        );

        $update = WebhookMockHelper::mockCallbackQuery('lang_en');

        $response = $this->postJson(self::URL, $update);

        $response->assertStatus(200);

        // The legacy controller handled the callback: the state advanced to
        // waiting for the bot token with the selected language recorded.
        $state = BotMotherStateHelper::getState(self::CHAT_ID);
        $this->assertNotNull($state, 'Legacy controller should have advanced the wizard state');
        $this->assertSame(BotMotherStateHelper::STATE_WAITING_TOKEN, $state['state']);
        $this->assertSame('en', $state['data']['language'] ?? null);
    }

    /**
     * /start stays with the v2 workflow engine: it is NOT delegated to the
     * legacy controller (no bot_logs row) and it initializes the workflow
     * creation state.
     */
    public function test_start_command_stays_with_workflow_engine(): void
    {
        $update = WebhookMockHelper::mockStartCommand();

        $response = $this->postJson(self::URL, $update);

        $response->assertStatus(200);

        // No delegation happened (the legacy controller would have logged).
        $this->assertDatabaseCount('bot_logs', 0);

        // The workflow engine initialized the creation state itself.
        $this->assertSame(
            'workflow_creation',
            BotMotherStateHelper::getCurrentState(self::CHAT_ID)
        );
    }
}
