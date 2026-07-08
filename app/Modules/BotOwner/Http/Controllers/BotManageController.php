<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Modules\BotOwner\Contracts\BotManageServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\View\View;

class BotManageController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotManageServiceInterface $manageService,
    ) {
    }

    public function index(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();

        // Permission check: only bot owner can manage
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $data = $this->manageService->getManageData($bot, $owner);

        return view('bot-owner.manage.index', [
            'owner' => $owner,
            'bot' => $data['bot'],
            'stats' => $data['stats'],
            'sections' => $data['sections'],
        ]);
    }

    public function stats(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $stats = $this->manageService->getBotStats($bot);

        return view('bot-owner.manage.stats', [
            'owner' => $owner,
            'bot' => $bot,
            'stats' => $stats,
        ]);
    }
}
