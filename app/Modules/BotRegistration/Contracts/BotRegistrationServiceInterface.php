<?php

namespace App\Modules\BotRegistration\Contracts;

use App\Models\Bot;
use App\Models\WebhookEndpoint;

interface BotRegistrationServiceInterface
{
    /**
     * @return array{success: bool, message: string, bot?: Bot}
     */
    public function registerBot(
        string $token,
        string $endpointId,
        string $platform,
        string $language,
        int $botMotherId,
        ?int $botOwnerId = null,
        ?string $ownerChatId = null,
    ): array;

    public function getEndpoint(string $endpointId): ?WebhookEndpoint;

    /**
     * @return array<int, string>
     */
    public function getSupportedPlatforms(WebhookEndpoint $endpoint): array;
}
