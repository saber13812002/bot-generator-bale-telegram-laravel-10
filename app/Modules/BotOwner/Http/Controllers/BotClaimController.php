<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotClaimServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotClaimController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotClaimServiceInterface $claimService,
    ) {
    }

    public function index(): View
    {
        $owner = $this->authService->currentOwner();
        $claimableBots = $this->claimService->getClaimableBots($owner);
        $pendingClaims = $this->claimService->getPendingClaims($owner);

        // Find the Admin Bots bot for verification link
        $adminBot = \App\Models\Bot::where('endpoint_id', 'admin-bots')->first();
        $adminBotLink = null;
        if ($adminBot) {
            $adminBotName = $adminBot->bale_bot_name ?? $adminBot->telegram_bot_name;
            $isBale = $adminBot->type === 'bale' || $adminBot->bale_bot_name;
            $adminBotLink = $isBale
                ? 'https://ble.ir/' . ($adminBot->bale_bot_name ?? $adminBotName)
                : 'https://t.me/' . ($adminBot->telegram_bot_name ?? $adminBotName);
        }

        return view('bot-owner.claim', [
            'owner' => $owner,
            'claimableBots' => $claimableBots,
            'pendingClaims' => $pendingClaims,
            'adminBotLink' => $adminBotLink,
            'adminBotName' => $adminBot?->bale_bot_name ?? $adminBot?->telegram_bot_name ?? 'Admin Bots',
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $owner = $this->authService->currentOwner();

        $validated = $request->validate([
            'bot_id' => 'required|integer|exists:bots,id',
            'claim_type' => 'required|in:owner,admin',
        ]);

        $result = $this->claimService->generateClaim(
            $owner,
            $validated['bot_id'],
            $validated['claim_type']
        );

        return redirect()
            ->route('bot-owner.claim')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $owner = $this->authService->currentOwner();
        $pendingClaims = $this->claimService->getPendingClaims($owner);

        return response()->json([
            'has_pending' => $pendingClaims->isNotEmpty(),
            'claims' => $pendingClaims->map(fn($c) => [
                'id' => $c->id,
                'bot_id' => $c->bot_id,
                'bot_name' => $c->bot->bale_bot_name ?? $c->bot->telegram_bot_name ?? 'Bot #'.$c->bot->id,
                'code' => $c->verification_code,
                'status' => $c->status,
                'expires_at' => $c->expires_at->format('Y-m-d H:i:s'),
            ]),
        ]);
    }
}
