<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotRegistration\Contracts\BotRegistrationServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateBotController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotRegistrationServiceInterface $registrationService,
    ) {
    }

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

        $endpoint = $this->registrationService->getEndpoint($endpointId);
        if (!$endpoint) {
            return redirect()->route('bot-owner.dashboard')
                ->with('error', trans('bot-owner.endpoint_not_found'));
        }

        $platforms = $this->registrationService->getSupportedPlatforms($endpoint);

        return view('bot-owner.create', [
            'endpoint' => $endpoint,
            'platforms' => $platforms,
        ]);
    }

    public function store(Request $request, string $endpointId): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'platform' => ['required', 'in:telegram,bale'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $owner = $this->authService->currentOwner();
        $language = $request->input('language', 'fa');
        $botMotherId = (int) ($request->input('bot_mother_id', 1));

        $result = $this->registrationService->registerBot(
            $request->input('token'),
            $endpointId,
            $request->input('platform'),
            $language,
            $botMotherId,
            $owner->id,
            $owner->bale_chat_id,
        );

        return redirect()->route('bot-owner.dashboard')->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }
}
