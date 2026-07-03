<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\WebhookEndpoint;
use App\Modules\BotCreation\Contracts\BotCreationWorkflowInterface;
use App\Modules\BotCreation\Models\BotCreationSession;
use App\Modules\BotCreation\Models\FieldDefinition;
use App\Modules\BotCreation\Services\ChatRenderer;
use App\Modules\BotCreation\Services\FieldRegistry;
use App\Modules\BotCreation\Services\WebRenderer;
use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class BotCreationWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private BotCreationWorkflowInterface $workflowService;
    private WebRenderer $webRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflowService = $this->app->make(BotCreationWorkflowInterface::class);
        $this->webRenderer = $this->app->make(WebRenderer::class);
    }

    /** @test */
    public function web_renderer_generates_form_for_quran_bot(): void
    {
        $steps = $this->workflowService->getStepsForEndpoint('quran-bot');
        $fields = FieldDefinition::collectionFromArray($steps);

        $html = $this->webRenderer->renderSimpleForm(
            $fields->toArray(),
            'quran-bot',
            '/bots/create/quran-bot'
        );

        $this->assertStringContainsString('platform', $html);
        $this->assertStringContainsString('language', $html);
        $this->assertStringContainsString('token', $html);
    }

    /** @test */
    public function web_renderer_generates_wizard_form_for_complex_bot(): void
    {
        $steps = $this->workflowService->getStepsForEndpoint('presenter-bot');
        $fields = FieldDefinition::collectionFromArray($steps);

        $html = $this->webRenderer->renderWizardForm(
            $fields->toArray(),
            'presenter-bot',
            '/bots/create/presenter-bot'
        );

        $this->assertStringContainsString('wizard-step', $html);
        $this->assertStringContainsString('wizard-next', $html);
        $this->assertStringContainsString('wizard-form', $html);
    }

    /** @test */
    public function field_definition_parses_conditions_correctly(): void
    {
        $field = FieldDefinition::fromArray([
            'id' => 'test',
            'type' => 'text',
            'label' => 'Test',
            'condition' => [
                'field' => 'parent_field',
                'operator' => '=',
                'value' => 'show_me',
            ],
        ]);

        // Condition should show when parent matches
        $this->assertTrue($field->shouldShow(['parent_field' => 'show_me']));

        // Condition should not show when parent doesn't match
        $this->assertFalse($field->shouldShow(['parent_field' => 'other']));

        // No condition always shows
        $noCondition = FieldDefinition::fromArray([
            'id' => 'test2',
            'type' => 'text',
            'label' => 'Test2',
        ]);
        $this->assertTrue($noCondition->shouldShow([]));
    }

    /** @test */
    public function field_definition_handles_in_operator(): void
    {
        $field = FieldDefinition::fromArray([
            'id' => 'test',
            'type' => 'text',
            'label' => 'Test',
            'condition' => [
                'field' => 'type',
                'operator' => 'in',
                'value' => ['telegram', 'bale'],
            ],
        ]);

        $this->assertTrue($field->shouldShow(['type' => 'telegram']));
        $this->assertTrue($field->shouldShow(['type' => 'bale']));
        $this->assertFalse($field->shouldShow(['type' => 'gap']));
    }

    /** @test */
    public function workflow_session_expires_after_ttl(): void
    {
        $session = BotCreationSession::create([
            'endpoint_id' => 'test-bot',
            'channel' => 'web',
            'user_id' => 'user_1',
            'status' => BotCreationSession::STATUS_IN_PROGRESS,
            'current_step' => 0,
            'expires_at' => now()->subMinutes(5), // Already expired
        ]);

        $this->assertTrue($session->isExpired());
        $this->assertFalse($session->isCompleted());
    }

    /** @test */
    public function web_bot_owner_create_requires_auth(): void
    {
        $response = $this->get(route('bot-owner.create', 'quran-bot'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    /** @test */
    public function web_bot_owner_create_requires_pro(): void
    {
        $owner = BotOwner::factory()->create([
            'is_pro' => false,
            'status' => 'active',
        ]);

        Session::put('bot_owner_id', $owner->id);

        $response = $this->get(route('bot-owner.create', 'quran-bot'));

        $response->assertRedirect(route('bot-owner.dashboard'));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function web_bot_owner_can_see_wizard_form(): void
    {
        WebhookEndpoint::factory()->create([
            'endpoint_id' => 'quran-bot',
            'name' => 'Quran Bot',
            'description' => 'Test description',
            'is_active' => true,
        ]);

        $owner = BotOwner::factory()->create([
            'is_pro' => true,
            'pro_expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        Session::put('bot_owner_id', $owner->id);

        $response = $this->get(route('bot-owner.create', 'quran-bot'));

        $response->assertStatus(200);
        $response->assertSee('Quran Bot');
        $response->assertSee('platform');
        $response->assertSee('token');
    }

    /** @test */
    public function language_list_field_resolves_options(): void
    {
        $field = FieldDefinition::fromArray([
            'id' => 'language',
            'type' => 'select',
            'label' => 'Language',
            'options' => [
                ['value' => 'fa', 'label_fa' => 'فارسی', 'label_en' => 'Persian'],
                ['value' => 'en', 'label_fa' => 'انگلیسی', 'label_en' => 'English'],
            ],
        ]);

        $this->assertCount(2, $field->options);
        $this->assertEquals('fa', $field->options[0]['value']);
    }
}
