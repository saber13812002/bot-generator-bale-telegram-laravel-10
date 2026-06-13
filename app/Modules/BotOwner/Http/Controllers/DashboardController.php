<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerDashboardServiceInterface;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotOwnerDashboardServiceInterface $dashboardService,
    ) {
    }

    public function index(): View
    {
        $owner = $this->authService->currentOwner();
        $data = $this->dashboardService->getDashboardData($owner);

        return view('bot-owner.dashboard', [
            'owner' => $owner,
            'bots' => $data['bots'],
            'stats' => $data['stats'],
        ]);
    }
}
