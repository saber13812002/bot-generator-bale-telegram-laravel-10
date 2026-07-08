<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Modules\BotOwner\Contracts\BotAdminPanelUserServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotAdminPanelUserController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotAdminPanelUserServiceInterface $adminPanelService,
    ) {
    }

    public function index(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $admins = $this->adminPanelService->getAdmins($bot);

        return view('bot-owner.manage.admin-panel-users', [
            'owner' => $owner,
            'bot' => $bot,
            'admins' => $admins,
            'isOwner' => $bot->bot_owner_id === $owner->id,
        ]);
    }

    public function store(Request $request, Bot $bot): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        // Only the bot owner can add admins
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $validated = $request->validate([
            'bot_owner_id' => 'required|integer|exists:bot_owners,id',
        ]);

        $result = $this->adminPanelService->addAdmin($bot, $validated['bot_owner_id'], $owner);

        return redirect()
            ->route('bot-owner.manage.admin-panel-users', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Bot $bot, int $admin): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $result = $this->adminPanelService->removeAdmin($bot, $admin, $owner);

        return redirect()
            ->route('bot-owner.manage.admin-panel-users', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $owner = $this->authService->currentOwner();
        $query = $request->query('q', '');

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $owners = $this->adminPanelService->searchOwners($query);

        return response()->json($owners);
    }
}
