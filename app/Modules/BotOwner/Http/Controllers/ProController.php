<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotOwnerProServiceInterface $proService,
    ) {
    }

    public function request(Request $request): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        $result = $this->proService->requestPro($owner->id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }
}
