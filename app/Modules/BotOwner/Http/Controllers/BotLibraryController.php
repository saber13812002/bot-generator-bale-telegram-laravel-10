<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotLibraryServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotLibraryController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotLibraryServiceInterface $libraryService,
    ) {
    }

    public function index(Request $request): View
    {
        $owner = $this->authService->currentOwner();
        $type = $request->query('type');
        $relation = $request->query('relation');
        $page = $request->query('page', 1);

        $bots = $this->libraryService->getBots($owner, $type, $relation, 15);
        $botTypes = $this->libraryService->getOwnerBotTypes($owner);

        return view('bot-owner.library', [
            'owner' => $owner,
            'bots' => $bots,
            'botTypes' => $botTypes,
            'currentType' => $type,
            'currentRelation' => $relation,
        ]);
    }
}
