<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotCreation\Contracts\BotCreationWorkflowInterface;
use App\Modules\BotCreation\Models\BotCreationSession;
use App\Modules\BotCreation\Models\FieldDefinition;
use App\Modules\BotCreation\Services\WebRenderer;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateBotController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotCreationWorkflowInterface $workflowService,
        private readonly WebRenderer $webRenderer,
    ) {}

    /**
     * Show the bot creation form (wizard).
     */
    public function show(string $endpointId): View|RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        if (!$owner) {
            return redirect()->route('bot-owner.login', [
                'redirect' => route('bot-owner.create', $endpointId),
            ]);
        }

        if (!$owner->hasActivePro()) {
            return redirect()->route('bot-owner.dashboard')
                ->with('error', trans('bot-owner.pro_required'));
        }

        $endpoint = $this->workflowService->getAvailableEndpoints();
        $endpoint = collect($endpoint)->firstWhere('endpoint_id', $endpointId);

        if (!$endpoint) {
            return redirect()->route('bot-owner.dashboard')
                ->with('error', trans('bot-owner.endpoint_not_found'));
        }

        // Get the wizard steps for this endpoint
        $steps = $this->workflowService->getStepsForEndpoint($endpointId);
        $fields = FieldDefinition::collectionFromArray($steps);

        return view('bot-owner.create', [
            'endpoint' => (object) $endpoint,
            'fields' => $fields,
        ]);
    }

    /**
     * Store the bot creation request.
     */
    public function store(Request $request, string $endpointId): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        if (!$owner) {
            return redirect()->route('bot-owner.login');
        }

        // Start a workflow session
        $session = $this->workflowService->startSession(
            $endpointId,
            'web',
            (string) $owner->id
        );

        $session->bot_owner_id = $owner->id;
        $session->save();

        // Get all steps and process each one with the request data
        $steps = $this->workflowService->getStepsForEndpoint($endpointId);
        $fields = FieldDefinition::collectionFromArray($steps);

        $hasError = false;
        $firstError = null;

        foreach ($fields as $field) {
            $value = $request->input($field->id);
            $result = $this->workflowService->processStep($session, $value);

            if (!empty($result['error'])) {
                $hasError = true;
                $firstError = $result['error'];
                break;
            }

            if ($result['completed']) {
                break;
            }
        }

        // If not completed yet, process the remaining steps
        if (!$hasError && !$session->isCompleted()) {
            // Process through remaining steps until completion
            $finalResult = $this->continueToCompletion($session);
            if (!$finalResult['success']) {
                $hasError = true;
                $firstError = $finalResult['error'] ?? trans('bot-owner.bot_creation_failed');
            }
        }

        if ($hasError) {
            return redirect()->route('bot-owner.create', $endpointId)
                ->with('error', $firstError)
                ->withInput();
        }

        // Reload session to get final result
        $session->refresh();

        if ($session->isCompleted() && $session->bot_id) {
            return redirect()->route('bot-owner.dashboard')
                ->with('success', trans('bot-owner.bot_created', ['name' => $session->bot_id]));
        }

        return redirect()->route('bot-owner.dashboard')
            ->with('error', trans('bot-owner.bot_creation_failed'));
    }

    /**
     * Continue processing steps until session completion.
     */
    private function continueToCompletion(BotCreationSession $session): array
    {
        $maxIterations = 50;
        $iteration = 0;

        while (!$session->isCompleted() && $iteration < $maxIterations) {
            $stepInfo = $this->workflowService->getCurrentStep($session);
            if ($stepInfo === null) {
                // No more steps, trigger completion
                $result = $this->workflowService->processStep($session, null);
                if ($result['completed']) {
                    return $result;
                }
                break;
            }

            // For fields with defaults, use the default value
            $field = $stepInfo['field'];
            $defaultValue = $field->meta['default'] ?? null;

            if ($defaultValue !== null) {
                $result = $this->workflowService->processStep($session, $defaultValue);
                if (!empty($result['error'])) {
                    return ['success' => false, 'error' => $result['error']];
                }
                if ($result['completed']) {
                    return $result;
                }
            } else {
                // Skip optional fields
                if (!$field->required) {
                    $result = $this->workflowService->processStep($session, null);
                    if (!empty($result['error'])) {
                        return ['success' => false, 'error' => $result['error']];
                    }
                    if ($result['completed']) {
                        return $result;
                    }
                } else {
                    // Required field without value - error
                    return ['success' => false, 'error' => "فیلد {$field->label} الزامی است."];
                }
            }

            $iteration++;
        }

        // Final check
        $session->refresh();
        return [
            'success' => $session->isCompleted(),
            'error' => $session->isCompleted() ? null : 'خطا در تکمیل فرآیند',
        ];
    }
}
