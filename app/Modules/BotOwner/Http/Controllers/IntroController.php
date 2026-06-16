<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Services\WebhookEndpointCatalogService;
use Illuminate\View\View;

class IntroController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly WebhookEndpointCatalogService $catalogService,
    ) {
    }

    public function index(): View
    {
        return view('bot-owner.intro', [
            'endpoints' => $this->catalogService->getActiveEndpoints(forOwnerIntro: true),
            'owner' => $this->authService->currentOwner(),
        ]);
    }
}
