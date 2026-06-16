<?php

namespace App\Http\Controllers;

use App\Services\WebhookEndpointCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __construct(
        private readonly WebhookEndpointCatalogService $catalogService,
    ) {
    }

    public function index(): View
    {
        return view('welcome', [
            'bots' => $this->catalogService->getActiveEndpoints(),
        ]);
    }

    public function show(string $endpointId): View|RedirectResponse
    {
        $bot = $this->catalogService->getActiveEndpoints()
            ->firstWhere('endpoint_id', $endpointId);

        if (!$bot) {
            abort(404, 'ربات یافت نشد');
        }

        $relatedBots = $bot->getRelatedBots(3);

        return view('bot.show', [
            'bot' => $bot,
            'relatedBots' => $relatedBots,
        ]);
    }
}
