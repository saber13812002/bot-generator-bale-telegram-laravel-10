<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\WebhookEndpoint;
use App\Modules\BotCreation\Contracts\BotCreationWorkflowInterface;
use App\Modules\BotCreation\Models\BotCreationSession;
use App\Modules\BotCreation\Models\FieldDefinition;
use App\Modules\BotCreation\Services\BotCreationWorkflowService;
use App\Modules\BotCreation\Services\FieldRegistry;
use App\Modules\BotRegistration\Contracts\BotRegistrationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotCreationWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotCreationWorkflowService $workflowService;
    private FieldRegistry $fieldRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fieldRegistry = new FieldRegistry();
        $this->fieldRegistry->registerDefaults();

        $registrationMock = $this->createMock(BotRegistrationServiceInterface::class);
        $registrationMock->method('registerBot')
            ->willReturn([
                'success' => true,
                'message' => 'Bot created',
                'bot' => Bot::factory()->make(['id' => 1]),
            ]);

        $this->workflowService = new BotCreationWorkflowService(
            $this->fieldRegistry,
            $registrationMock
        );
    }

    /** @test */
    public function it_can_start_a_session(): void
    {
        $session = $this->workflowService->startSession('quran-bot', 'web', 'user_1');

        $this->assertNotNull($session);
        $this->assertEquals('quran-bot', $session->endpoint_id);
        $this->assertEquals('web', $session->channel);
        $this->assertEquals('user_1', $session->user_id);
        $this->assertEquals(BotCreationSession::STATUS_IN_PROGRESS, $session->status);
        $this->assertEquals(0, $session->current_step);
    }

    /** @test */
    public function it_returns_default_steps_for_unknown_endpoint(): void
    {
        $steps = $this->workflowService->getStepsForEndpoint('non-existent');

        $this->assertNotEmpty($steps);
        $this->assertCount(3, $steps); // platform, language, token
        $this->assertEquals('platform', $steps[0]['id']);
        $this->assertEquals('language', $steps[1]['id']);
        $this->assertEquals('token', $steps[2]['id']);
    }

    /** @test */
    public function it_returns_first_step_when_session_starts(): void
    {
        $session = $this->workflowService->startSession('quran-bot', 'web', 'user_1');
        $stepInfo = $this->workflowService->getCurrentStep($session);

        $this->assertNotNull($stepInfo);
        $this->assertEquals(0, $stepInfo['current_step']);
        $this->assertEquals(3, $stepInfo['total_steps']);
        $this->assertEquals('platform', $stepInfo['field']->id);
    }

    /** @test */
    public function it_processes_platform_step_correctly(): void
    {
        $session = $this->workflowService->startSession('quran-bot', 'web', 'user_1');

        // Process platform step
        $result = $this->workflowService->processStep($session, 'telegram');

        $this->assertFalse($result['completed']);
        $this->assertNull($result['error']);
        $this->assertNotNull($result['next_step']);
        $this->assertEquals('language', $result['next_step']['field']->id);

        // Verify data was collected
        $session->refresh();
        $this->assertEquals('telegram', $session->getCollected('platform'));
    }

    /** @test */
    public function it_rejects_invalid_platform(): void
    {
        $session = $this->workflowService->startSession('quran-bot', 'web', 'user_1');

        $result = $this->workflowService->processStep($session, 'invalid_platform');

        $this->assertFalse($result['completed']);
        $this->assertNotNull($result['error']);
        $this->assertEquals('platform', $result['field_id']);
    }

    /** @test */
    public function it_processes_all_steps_and_completes(): void
    {
        $session = $this->workflowService->startSession('quran-bot', 'web', 'user_1');

        // Step 1: platform
        $result = $this->workflowService->processStep($session, 'telegram');
        $this->assertFalse($result['completed']);

        // Step 2: language
        $result = $this->workflowService->processStep($session, 'fa');
        $this->assertFalse($result['completed']);

        // Step 3: token (will try to register bot - mock returns success)
        $result = $this->workflowService->processStep($session, '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');

        $this->assertTrue($result['completed']);
        $this->assertTrue($result['success']);

        // Verify session completed
        $session->refresh();
        $this->assertEquals(BotCreationSession::STATUS_COMPLETED, $session->status);
    }

    /** @test */
    public function it_respects_field_conditions(): void
    {
        $session = $this->workflowService->startSession('quran-bot', 'web', 'user_1');

        // Get steps - should not show conditional fields for quran-bot
        $steps = $this->workflowService->getStepsForEndpoint('quran-bot');
        $fields = FieldDefinition::collectionFromArray($steps);

        $hasPresenterField = $fields->first(fn (FieldDefinition $f) => $f->id === 'presenter_content');
        $this->assertNull($hasPresenterField);
    }

    /** @test */
    public function it_handles_select_field_processing(): void
    {
        $selectField = FieldDefinition::fromArray([
            'id' => 'test_select',
            'type' => 'select',
            'label' => 'Test',
            'options' => [
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b', 'label' => 'B'],
            ],
        ]);

        $handler = $this->fieldRegistry->get('select');

        // Test valid value
        $processed = $handler->processValue('b', $selectField);
        $validation = $handler->validate($processed, $selectField);
        $this->assertTrue($validation['valid']);

        // Test invalid value
        $validation = $handler->validate('c', $selectField);
        $this->assertFalse($validation['valid']);
    }

    /** @test */
    public function it_handles_text_field_processing(): void
    {
        $textField = FieldDefinition::fromArray([
            'id' => 'test_text',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
        ]);

        $handler = $this->fieldRegistry->get('text');

        // Test valid text
        $result = $handler->validate('some value', $textField);
        $this->assertTrue($result['valid']);

        // Test empty required
        $result = $handler->validate('', $textField);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_handles_yes_no_field_processing(): void
    {
        $yesNoField = FieldDefinition::fromArray([
            'id' => 'test_yesno',
            'type' => 'yes_no',
            'label' => 'Test',
        ]);

        $handler = $this->fieldRegistry->get('yes_no');

        // Test yes
        $processed = $handler->processValue('yes', $yesNoField);
        $this->assertTrue($processed);

        // Test no
        $processed = $handler->processValue('no', $yesNoField);
        $this->assertFalse($processed);
    }

    /** @test */
    public function it_handles_number_field_processing(): void
    {
        $numberField = FieldDefinition::fromArray([
            'id' => 'test_number',
            'type' => 'number',
            'label' => 'Test',
        ]);

        $handler = $this->fieldRegistry->get('number');

        // Test valid number
        $processed = $handler->processValue('42', $numberField);
        $this->assertEquals(42, $processed);

        // Test invalid
        $result = $handler->validate('not_a_number', $numberField);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_abandons_previous_sessions_on_new_start(): void
    {
        // Create first session
        $session1 = $this->workflowService->startSession('quran-bot', 'web', 'user_1');
        $this->assertEquals(BotCreationSession::STATUS_IN_PROGRESS, $session1->status);

        // Start second session for same user - should abandon first
        $session2 = $this->workflowService->startSession('quran-bot', 'web', 'user_1');

        $session1->refresh();
        $this->assertEquals(BotCreationSession::STATUS_ABANDONED, $session1->status);
        $this->assertEquals(BotCreationSession::STATUS_IN_PROGRESS, $session2->status);
    }

    /** @test */
    public function it_provides_available_endpoints(): void
    {
        WebhookEndpoint::factory()->create([
            'endpoint_id' => 'test-bot',
            'name' => 'Test Bot',
            'is_active' => true,
        ]);

        $endpoints = $this->workflowService->getAvailableEndpoints();

        $this->assertNotEmpty($endpoints);
        $this->assertContains('test-bot', array_column($endpoints, 'endpoint_id'));
    }
}
