<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\View\View;

class BotSettingsController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
    ) {
    }

    public function index(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $bot->load('webhookEndpoint');

        // Try to load type-specific view
        $typeView = 'bot-owner.manage.types.' . $bot->endpoint_id . '.settings';
        $hasTypeSpecificView = view()->exists($typeView);

        return view('bot-owner.manage.settings', [
            'owner' => $owner,
            'bot' => $bot,
            'hasTypeSpecificView' => $hasTypeSpecificView,
            'typeView' => $typeView,
        ]);
    }
}
