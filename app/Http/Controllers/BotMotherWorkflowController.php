<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\BotMotherStateHelper;
use App\Http\Requests\BotRequest;
use App\Modules\BotCreation\Contracts\BotCreationWorkflowInterface;
use App\Modules\BotCreation\Models\BotCreationSession;
use App\Modules\BotCreation\Services\ChatRenderer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

/**
 * Unified Bot Mother controller using the new BotCreationWorkflow engine.
 * This replaces the complex state machine in BotMotherController with
 * a generic workflow engine that works identically for chat and web paths.
 */
class BotMotherWorkflowController extends Controller
{
    public function __construct(
        private readonly BotCreationWorkflowInterface $workflowService,
        private readonly ChatRenderer $chatRenderer,
    ) {}

    /**
     * Handle bot mother webhook using the unified workflow.
     * Only handles /start and bot creation flow. Admin commands
     * (statistics, broadcast, etc.) remain in BotMotherController.
     */
    public function webhook(Request $request)
    {
        Log::info('🤖 [BotMotherWorkflow] Webhook received', [
            'has_origin' => $request->has('origin'),
            'has_bot_mother_id' => $request->has('bot_mother_id'),
            'origin' => $request->input('origin'),
        ]);

        if (!$request->has('origin') || !$request->has('bot_mother_id')) {
            return response('ok', 200);
        }

        $type = $request->input('origin');
        $botMotherId = $request->input('bot_mother_id');
        $token = $request->has('token')
            ? $request->input('token')
            : ($type === 'bale'
                ? env('BOT_MOTHER_TOKEN_BALE')
                : env('BOT_MOTHER_TOKEN_TELEGRAM'));

        if (empty($token)) {
            Log::error('❌ [BotMotherWorkflow] No token available');
            return response('ok', 200);
        }

        $bot = new Telegram($token, $type);
        $update = $request->json()->all() ?? $request->all();
        $bot->setData($update);
        $chatId = $this->extractChatId($update);

        if (!$chatId) {
            return response('ok', 200);
        }

        // Admin check
        if (!AdminHelper::isAdmin($chatId)) {
            BotHelper::sendMessage($bot, '❌ شما دسترسی به این ربات ندارید.');
            return;
        }

        // Handle callback queries
        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($bot, $update, $type, $botMotherId, $request);
        }

        $text = $bot->Text();
        $currentState = BotMotherStateHelper::getCurrentState($chatId);

        // /start or "ساختن" command - kept by the new workflow engine
        if (in_array(mb_strtolower($text ?? ''), ['/start', 'ساختن', '/new', 'new'], true)) {
            $this->handleStart($bot, $type, $botMotherId, $chatId);
            return;
        }

        // Any other slash command (statistics, broadcast, confirm/reject commands,
        // content management, ...) must reach the legacy controller, even while
        // a creation workflow is active.
        if (is_string($text) && str_starts_with($text, '/')) {
            return $this->delegateToLegacyMotherController($request);
        }

        // Handle active creation session (plain text answers to workflow steps)
        if ($currentState === 'workflow_creation') {
            $this->handleWorkflowStep($bot, $text, $chatId, $type);
            return;
        }

        // Everything else - forward to the legacy BotMotherController so it can
        // answer it properly (and log it) instead of replying "invalid command".
        return $this->delegateToLegacyMotherController($request);
    }

    /**
     * Delegate a non-workflow message/callback to the legacy BotMotherController.
     *
     * The v2 webhook already validated origin/bot_mother_id/token and resolved
     * the chat id, so we build the BotRequest directly (skipping
     * validateResolved) and let the legacy controller do its full dispatch,
     * logging and admin checks.
     */
    private function delegateToLegacyMotherController(Request $request)
    {
        $botRequest = BotRequest::createFrom($request);
        // Ensure the full update payload (JSON or form encoded) is available
        // as form input on the delegated request.
        $botRequest->request->add($request->all());

        return app(BotMotherController::class)->botMotherWebhook($botRequest);
    }

    /**
     * Handle /start command - show endpoint list and start workflow.
     */
    private function handleStart(Telegram $bot, string $type, int $botMotherId, string $chatId): void
    {
        BotMotherStateHelper::clearState($chatId);

        $endpoints = $this->workflowService->getAvailableEndpoints();
        $this->chatRenderer->renderIntro($bot, $chatId, $endpoints);

        BotMotherStateHelper::setState($chatId, 'workflow_creation', [
            'bot_mother_id' => $botMotherId,
            'type' => $type,
            'awaiting' => 'endpoint_selection',
        ]);
    }

    /**
     * Handle a step in the active creation workflow.
     */
    private function handleWorkflowStep(Telegram $bot, ?string $text, string $chatId, string $type): void
    {
        $stateData = BotMotherStateHelper::getData($chatId);
        $awaiting = $stateData['awaiting'] ?? null;

        if ($awaiting === 'endpoint_selection') {
            $this->handleEndpointSelection($bot, $text, $chatId, $type, $stateData);
            return;
        }

        if ($awaiting === 'step_answer') {
            $this->handleStepAnswer($bot, $text, $chatId, $type, $stateData);
            return;
        }
    }

    /**
     * Handle endpoint selection from user.
     */
    private function handleEndpointSelection(Telegram $bot, ?string $text, string $chatId, string $type, array $stateData): void
    {
        if (!is_numeric($text)) {
            BotHelper::sendMessageByChatId($bot, $chatId, '❌ لطفاً شماره endpoint را ارسال کنید.');
            return;
        }

        $endpoints = $this->workflowService->getAvailableEndpoints();
        $selectedIndex = (int) $text - 1;

        if ($selectedIndex < 0 || $selectedIndex >= count($endpoints)) {
            BotHelper::sendMessageByChatId($bot, $chatId, '❌ شماره نامعتبر است.');
            return;
        }

        $selectedEndpoint = $endpoints[$selectedIndex];
        $endpointId = $selectedEndpoint['endpoint_id'] ?? $selectedEndpoint['id'];

        // Start a workflow session
        try {
            $session = $this->workflowService->startSession($endpointId, $type, $chatId);
        } catch (Exception $e) {
            BotHelper::sendMessageByChatId($bot, $chatId, '❌ خطا در شروع فرآیند: ' . $e->getMessage());
            return;
        }

        // Render the first step
        $stepInfo = $this->workflowService->getCurrentStep($session);

        if ($stepInfo === null) {
            BotHelper::sendMessageByChatId($bot, $chatId, '✅ این ربات نیازی به تنظیمات اضافی ندارد.');
            $session->status = BotCreationSession::STATUS_COMPLETED;
            $session->save();
            return;
        }

        $this->chatRenderer->renderStep($bot, $chatId, $stepInfo['field'], [
            'collected_count' => 0,
            'total_steps' => $stepInfo['total_steps'],
        ]);

        // Store session info in state
        BotMotherStateHelper::setState($chatId, 'workflow_creation', array_merge($stateData, [
            'awaiting' => 'step_answer',
            'session_id' => $session->id,
        ]));
    }

    /**
     * Handle a step answer from the user.
     */
    private function handleStepAnswer(Telegram $bot, ?string $text, string $chatId, string $type, array $stateData): void
    {
        $sessionId = $stateData['session_id'] ?? null;
        if (!$sessionId) {
            BotHelper::sendMessageByChatId($bot, $chatId, '❌ نشست منقضی شده. لطفاً /start را بزنید.');
            BotMotherStateHelper::clearState($chatId);
            return;
        }

        $session = BotCreationSession::find($sessionId);
        if (!$session || $session->isExpired() || $session->isCompleted()) {
            BotHelper::sendMessageByChatId($bot, $chatId, '❌ نشست منقضی شده. لطفاً /start را بزنید.');
            BotMotherStateHelper::clearState($chatId);
            return;
        }

        // Process the step
        $result = $this->workflowService->processStep($session, $text);

        if ($result['completed']) {
            // Session completed (success or failure)
            $this->chatRenderer->renderCompletion($bot, $chatId, $result);
            BotMotherStateHelper::clearState($chatId);
            return;
        }

        if ($result['error']) {
            // Step had an error - re-render
            $stepInfo = $this->workflowService->getCurrentStep($session);
            if ($stepInfo) {
                $this->chatRenderer->renderStepError($bot, $chatId, $result['error'], $stepInfo['field']);
            }
            return;
        }

        // Step was successful - render next step
        $nextStep = $result['next_step'];
        if ($nextStep) {
            // Refresh session for updated data
            $session->refresh();

            $this->chatRenderer->renderStep($bot, $chatId, $nextStep['field'], [
                'collected_count' => count($session->collected_data ?? []),
                'total_steps' => $nextStep['total_steps'],
            ]);
        }
    }

    /**
     * Handle callback queries (button presses).
     */
    private function handleCallbackQuery(Telegram $bot, array $update, string $type, int $botMotherId, Request $request): void
    {
        $callbackQuery = $update['callback_query'];
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackChatId = $callbackQuery['message']['chat']['id'] ?? null;

        if (!$callbackChatId) {
            return;
        }

        // Handle workflow callback data: wf:{field_id}:{value}
        if (str_starts_with($callbackData, 'wf:')) {
            // Answer workflow callback immediately
            $bot->answerCallbackQuery([
                'callback_query_id' => $callbackQuery['id'],
            ]);

            $parts = explode(':', $callbackData);
            $fieldId = $parts[1] ?? null;
            $value = $parts[2] ?? null;

            if ($fieldId && $value !== null) {
                $this->handleStepAnswer($bot, $value, (string) $callbackChatId, $type, BotMotherStateHelper::getData($callbackChatId));
            }
            return;
        }

        // Other callbacks (language selection, content menu, broadcast, ...)
        // belong to the legacy controller. Do NOT answer the callback here -
        // the legacy controller answers callbacks it handles itself.
        $this->delegateToLegacyMotherController($request);
    }

    /**
     * Extract chat ID from update data.
     */
    private function extractChatId(array $update): ?string
    {
        if (isset($update['callback_query'])) {
            return $update['callback_query']['message']['chat']['id']
                ?? $update['callback_query']['from']['id']
                ?? null;
        }

        if (isset($update['message'])) {
            return $update['message']['chat']['id'] ?? null;
        }

        if (isset($update['edited_message'])) {
            return $update['edited_message']['chat']['id'] ?? null;
        }

        return null;
    }
}
