<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Modules\BotOwner\Contracts\BotItemsServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotItemsController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotItemsServiceInterface $itemsService,
    ) {
    }

    public function index(Request $request, Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $categoryId = $request->query('category_id');
        $items = $this->itemsService->getItems($bot, $categoryId);

        return view('bot-owner.manage.items', [
            'owner' => $owner,
            'bot' => $bot,
            'items' => $items,
            'categoryId' => $categoryId,
        ]);
    }

    public function reorder(Request $request, Bot $bot): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if($bot->bot_owner_id !== $owner->id, 403);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer|exists:content_items,id',
            'order.*.queue_order' => 'required|integer|min:0',
        ]);

        $result = $this->itemsService->reorder($bot, $validated['order']);

        return redirect()
            ->route('bot-owner.manage.items', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
