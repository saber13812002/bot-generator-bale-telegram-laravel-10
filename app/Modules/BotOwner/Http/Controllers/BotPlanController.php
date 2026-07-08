<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\LibraryPlanRequest;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotOwner\Contracts\BotPlanServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BotPlanController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotPlanServiceInterface $planService,
    ) {
    }

    public function index(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $requests = $this->planService->getPendingRequests($bot);

        return view('bot-owner.manage.plans', [
            'owner' => $owner,
            'bot' => $bot,
            'requests' => $requests,
        ]);
    }

    public function approve(Bot $bot, LibraryPlanRequest $planRequest): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $result = $this->planService->approve($planRequest, $owner);

        return redirect()
            ->route('bot-owner.manage.plans', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Bot $bot, LibraryPlanRequest $planRequest): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $result = $this->planService->reject($planRequest, $owner);

        return redirect()
            ->route('bot-owner.manage.plans', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
