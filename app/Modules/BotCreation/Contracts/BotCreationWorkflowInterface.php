<?php

namespace App\Modules\BotCreation\Contracts;

use App\Modules\BotCreation\Models\BotCreationSession;
use App\Models\WebhookEndpoint;

interface BotCreationWorkflowInterface
{
    /**
     * Start a new bot creation session for the given endpoint.
     */
    public function startSession(string $endpointId, string $channel, string $userId): BotCreationSession;

    /**
     * Get the current step definition for an active session.
     *
     * @return array{field: array, current_step: int, total_steps: int}|null
     */
    public function getCurrentStep(BotCreationSession $session): ?array;

    /**
     * Process a step answer for an active session.
     *
     * @return array{completed: bool, next_step: ?array, error: ?string, result?: array}
     */
    public function processStep(BotCreationSession $session, mixed $answer): array;

    /**
     * Get the collected (validated) data from a completed session.
     */
    public function getCollectedData(BotCreationSession $session): array;

    /**
     * Get the wizard steps definition for an endpoint.
     *
     * @return array<array>
     */
    public function getStepsForEndpoint(string $endpointId): array;

    /**
     * Get available endpoints for the creation wizard (intro screen).
     *
     * @return array<WebhookEndpoint>
     */
    public function getAvailableEndpoints(): array;
}
