<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\View\View;

class IntroController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
    ) {
    }

    public function index(): View
    {
        $endpoints = WebhookEndpoint::where('is_active', true)
            ->whereNotIn('endpoint_id', ['admin-bots', 'get-chat-id'])
            ->orderBy('name')
            ->get();

        return view('bot-owner.intro', [
            'endpoints' => $endpoints,
            'owner' => $this->authService->currentOwner(),
        ]);
    }
}
