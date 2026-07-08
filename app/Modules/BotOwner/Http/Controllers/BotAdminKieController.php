<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Modules\BotOwner\Contracts\BotAdminKieServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotAdminKieController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotAdminKieServiceInterface $adminKieService,
    ) {
    }

    public function index(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $requests = $this->adminKieService->getPendingRequests($bot);

        return view('bot-owner.manage.admin-kie', [
            'owner' => $owner,
            'bot' => $bot,
            'requests' => $requests,
        ]);
    }

    public function approve(Bot $bot, BotAdminKieRequest $request): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $result = $this->adminKieService->approve($request, $owner);

        return redirect()
            ->route('bot-owner.manage.admin-kie', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Bot $bot, BotAdminKieRequest $request): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $result = $this->adminKieService->reject($request, $owner);

        return redirect()
            ->route('bot-owner.manage.admin-kie', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
