<?php

namespace App\Modules\BotCreation\Services;

use App\Helpers\TokenHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use App\Models\WebhookEndpoint;
use App\Modules\BotCreation\Contracts\BotCreationWorkflowInterface;
use App\Modules\BotCreation\Models\BotCreationSession;
use App\Modules\BotCreation\Models\FieldDefinition;
use App\Modules\BotRegistration\Contracts\BotRegistrationServiceInterface;
use Illuminate\Support\Facades\Log;
use Telegram;

class BotCreationWorkflowService implements BotCreationWorkflowInterface
{
    private const DEFAULT_STEPS = [
        [
            'id' => 'platform',
            'type' => 'select',
            'label_fa' => 'نوع پیام‌رسان',
            'label_en' => 'Platform',
            'options' => [
                ['value' => 'telegram', 'label_fa' => 'تلگرام', 'label_en' => 'Telegram'],
                ['value' => 'bale', 'label_fa' => 'بله', 'label_en' => 'Bale'],
            ],
            'required' => true,
            'validation' => 'in:telegram,bale',
            'order' => 1,
        ],
        [
            'id' => 'language',
            'type' => 'select',
            'label_fa' => 'زبان ربات',
            'label_en' => 'Bot Language',
            'options_provider' => 'language_list',
            'required' => true,
            'validation' => 'string|max:10',
            'order' => 2,
        ],
        [
            'id' => 'token',
            'type' => 'text',
            'label_fa' => 'توکن ربات',
            'label_en' => 'Bot Token',
            'placeholder_fa' => 'توکن را از BotFather دریافت کنید',
            'placeholder_en' => 'Get token from BotFather',
            'help_fa' => 'توکن ربات خود را از BotFather در تلگرام یا ربات پدر در بله دریافت کنید',
            'help_en' => 'Get your bot token from BotFather on Telegram or GodFather on Bale',
            'required' => true,
            'async_validate' => true,
            'on_validate' => 'validate_token',
            'order' => 3,
        ],
    ];

    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
        private readonly BotRegistrationServiceInterface $registrationService,
    ) {}

    /**
     * Start a new bot creation session.
     */
    public function startSession(string $endpointId, string $channel, string $userId): BotCreationSession
    {
        // Clear any existing in-progress sessions for this user
        BotCreationSession::where('user_id', $userId)
            ->where('channel', $channel)
            ->where('status', BotCreationSession::STATUS_IN_PROGRESS)
            ->update(['status' => BotCreationSession::STATUS_ABANDONED]);

        $session = BotCreationSession::create([
            'endpoint_id' => $endpointId,
            'channel' => $channel,
            'user_id' => $userId,
            'status' => BotCreationSession::STATUS_IN_PROGRESS,
            'current_step' => 0,
            'collected_data' => [],
            'step_results' => [],
            'expires_at' => now()->addHours(2),
        ]);

        Log::info('[BotCreation] Session started', [
            'session_id' => $session->id,
            'endpoint_id' => $endpointId,
            'channel' => $channel,
            'user_id' => $userId,
        ]);

        return $session;
    }

    /**
     * Get the current step definition for an active session.
     */
    public function getCurrentStep(BotCreationSession $session): ?array
    {
        $steps = $this->getFilteredSteps($session);

        if ($session->current_step >= count($steps)) {
            return null; // All steps completed
        }

        $stepDef = $steps[$session->current_step];
        $field = FieldDefinition::fromArray($stepDef);

        return [
            'field' => $field,
            'current_step' => $session->current_step,
            'total_steps' => count($steps),
        ];
    }

    /**
     * Process a step answer. Returns array with completion status and optional next step.
     */
    public function processStep(BotCreationSession $session, mixed $answer): array
    {
        $stepInfo = $this->getCurrentStep($session);
        if ($stepInfo === null) {
            // All steps done, process completion
            return $this->completeSession($session);
        }

        $field = $stepInfo['field'];
        $fieldHandler = $this->fieldRegistry->get($field->type);

        // Validate the answer
        $processedValue = $fieldHandler->processValue($answer, $field);
        $validation = $fieldHandler->validate($processedValue, $field);

        if (!$validation['valid']) {
            return [
                'completed' => false,
                'next_step' => $stepInfo,
                'error' => $validation['message'],
                'field_id' => $field->id,
            ];
        }

        // Async validation if supported (e.g., token validation)
        if ($field->asyncValidate && $fieldHandler->supportsAsyncValidation()) {
            $asyncResult = $fieldHandler->asyncValidate($processedValue, $field);
            if (!$asyncResult['valid']) {
                return [
                    'completed' => false,
                    'next_step' => $stepInfo,
                    'error' => $asyncResult['message'],
                    'field_id' => $field->id,
                    'async_data' => $asyncResult['data'] ?? null,
                ];
            }

            // Store async validation data (e.g., bot info from getMe)
            if (!empty($asyncResult['data'])) {
                $session->setCollected($field->id . '_async_data', $asyncResult['data']);
            }
        }

        // Store collected value
        $session->setCollected($field->id, $processedValue);

        // Store step result
        $stepResults = $session->step_results ?? [];
        $stepResults[] = [
            'field_id' => $field->id,
            'type' => $field->type,
            'value' => $processedValue,
            'valid' => true,
        ];
        $session->step_results = $stepResults;

        // Advance to next step
        $session->current_step++;
        $session->save();

        // Check if there are more steps
        $nextStep = $this->getCurrentStep($session);
        if ($nextStep === null) {
            return $this->completeSession($session);
        }

        return [
            'completed' => false,
            'next_step' => $nextStep,
            'error' => null,
            'field_id' => $field->id,
        ];
    }

    /**
     * Finalize and create the bot from collected data.
     */
    private function completeSession(BotCreationSession $session): array
    {
        $collected = $session->collected_data ?? [];
        $endpointId = $session->endpoint_id;
        $platform = $collected['platform'] ?? 'telegram';
        $token = $collected['token'] ?? '';
        $language = $collected['language'] ?? 'fa';
        $botOwnerId = $session->bot_owner_id;

        try {
            // Register the bot via BotRegistrationService
            $result = $this->registrationService->registerBot(
                token: $token,
                endpointId: $endpointId,
                platform: $platform,
                language: $language,
                botMotherId: 1,
                botOwnerId: $botOwnerId,
            );

            if (!$result['success']) {
                $session->status = BotCreationSession::STATUS_ABANDONED;
                $session->error_message = $result['message'];
                $session->save();

                return [
                    'completed' => true,
                    'success' => false,
                    'error' => $result['message'],
                    'result' => $result,
                ];
            }

            // Store extra wizard data for endpoints that need it
            $this->storeExtraWizardData($session, $result['bot']);

            $session->status = BotCreationSession::STATUS_COMPLETED;
            $session->bot_id = $result['bot']->id;
            $session->save();

            Log::info('[BotCreation] Bot created successfully', [
                'session_id' => $session->id,
                'bot_id' => $result['bot']->id,
                'endpoint_id' => $endpointId,
                'platform' => $platform,
            ]);

            return [
                'completed' => true,
                'success' => true,
                'error' => null,
                'result' => $result,
                'bot' => $result['bot'],
                'collected_data' => $collected,
            ];

        } catch (\Exception $e) {
            $session->status = BotCreationSession::STATUS_ABANDONED;
            $session->error_message = $e->getMessage();
            $session->save();

            Log::error('[BotCreation] Bot creation failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'completed' => true,
                'success' => false,
                'error' => $e->getMessage(),
                'result' => ['success' => false, 'message' => $e->getMessage()],
            ];
        }
    }

    /**
     * Store extra wizard data for complex endpoints (presenter content, psychology questions, etc.)
     */
    private function storeExtraWizardData(BotCreationSession $session, Bot $bot): void
    {
        $collected = $session->collected_data ?? [];
        $endpointId = $session->endpoint_id;

        // Presenter bot: store content
        if ($endpointId === 'presenter-bot' && !empty($collected['presenter_content'])) {
            $content = $collected['presenter_content'];
            $items = is_array($content)
                ? array_map(fn ($line) => ['type' => 'text', 'content' => $line], $content)
                : [['type' => 'text', 'content' => (string) $content]];

            \App\Models\PresenterBot::updateOrCreate(
                ['bot_id' => $bot->id],
                [
                    'content' => is_array($content) ? implode("\n", $content) : (string) $content,
                    'items' => $items,
                ]
            );

            Log::info('[BotCreation] Presenter bot content stored', ['bot_id' => $bot->id]);
        }

        // Rating bot: store items
        if ($endpointId === 'rating-bot' && !empty($collected['rating_content'])) {
            $content = $collected['rating_content'];
            $items = is_array($content)
                ? array_map(fn ($line) => ['type' => 'text', 'content' => $line], $content)
                : [['type' => 'text', 'content' => (string) $content]];

            \App\Models\RatingBot::updateOrCreate(
                ['bot_id' => $bot->id],
                [
                    'content' => is_array($content) ? implode("\n", $content) : (string) $content,
                    'items' => $items,
                ]
            );

            Log::info('[BotCreation] Rating bot content stored', ['bot_id' => $bot->id]);
        }

        // Content submission: store channel/group config
        if ($endpointId === 'content-submission') {
            $config = [
                'channel_chat_id' => $collected['content_channel_id'] ?? null,
                'group_chat_id' => $collected['content_group_id'] ?? null,
                'required_approvals' => $collected['content_required_approvals'] ?? 0,
                'origin' => $collected['platform'] ?? 'telegram',
            ];

            \App\Models\ContentSubmissionBotConfig::updateOrCreate(
                ['bot_id' => $bot->id],
                $config
            );

            Log::info('[BotCreation] Content submission config stored', ['bot_id' => $bot->id]);
        }
    }

    /**
     * Get all collected data from a completed session.
     */
    public function getCollectedData(BotCreationSession $session): array
    {
        return $session->collected_data ?? [];
    }

    /**
     * Get the wizard steps definition for an endpoint, merging defaults with endpoint-specific steps.
     */
    public function getStepsForEndpoint(string $endpointId): array
    {
        $endpoint = WebhookEndpoint::where('endpoint_id', $endpointId)
            ->where('is_active', true)
            ->first();

        if (!$endpoint) {
            return self::DEFAULT_STEPS;
        }

        $endpointSteps = $endpoint->wizard_steps ?? [];

        // If no custom steps defined, return defaults
        if (empty($endpointSteps)) {
            return self::DEFAULT_STEPS;
        }

        // Merge: start with defaults, override/add from endpoint config
        $merged = self::DEFAULT_STEPS;
        $existingIds = array_column($merged, 'id');

        foreach ($endpointSteps as $step) {
            $stepId = $step['id'] ?? null;
            $idx = array_search($stepId, $existingIds, true);
            if ($idx !== false) {
                // Override existing step
                $merged[$idx] = array_merge($merged[$idx], $step);
            } else {
                // Add new step
                $merged[] = $step;
            }
        }

        // Sort by order
        usort($merged, fn ($a, $b) => ($a['order'] ?? 999) - ($b['order'] ?? 999));

        return $merged;
    }

    /**
     * Get the filtered steps for a session, respecting field conditions.
     */
    private function getFilteredSteps(BotCreationSession $session): array
    {
        $allSteps = $this->getStepsForEndpoint($session->endpoint_id);
        $collected = $session->collected_data ?? [];

        return array_values(array_filter($allSteps, function ($step) use ($collected) {
            $field = FieldDefinition::fromArray($step);
            return $field->shouldShow($collected);
        }));
    }

    /**
     * Get available endpoints for the creation wizard.
     */
    public function getAvailableEndpoints(): array
    {
        return WebhookEndpoint::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Validate a token via the messenger API (getMe).
     * Used for async validation of the 'token' field.
     */
    public function validateToken(string $token, string $platform): array
    {
        if (!TokenHelper::isToken($token, $platform)) {
            return ['valid' => false, 'message' => '❌ توکن نامعتبر است.'];
        }

        try {
            $bot = new Telegram($token, $platform);
            $getMe = $bot->getMe();

            if (!($getMe['ok'] ?? false)) {
                return ['valid' => false, 'message' => '❌ خطا در ارتباط با ربات: ' . ($getMe['description'] ?? 'خطای ناشناخته')];
            }

            return [
                'valid' => true,
                'message' => '✅ توکن معتبر است. ربات: @' . ($getMe['result']['username'] ?? 'N/A'),
                'data' => [
                    'username' => $getMe['result']['username'] ?? null,
                    'first_name' => $getMe['result']['first_name'] ?? null,
                    'bot_info' => $getMe['result'],
                ],
            ];

        } catch (\Exception $e) {
            return ['valid' => false, 'message' => '❌ خطا: ' . $e->getMessage()];
        }
    }
}
