<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\ContentCategory;
use App\Modules\BotOwner\Contracts\BotCategoryServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotCategoryController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotCategoryServiceInterface $categoryService,
    ) {
    }

    public function index(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $categories = $this->categoryService->getCategories($bot);

        return view('bot-owner.manage.categories', [
            'owner' => $owner,
            'bot' => $bot,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, Bot $bot): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $result = $this->categoryService->create($bot, $validated);

        return redirect()
            ->route('bot-owner.manage.categories', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function update(Request $request, Bot $bot, ContentCategory $category): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);
        abort_if($category->bot_id !== $bot->id, 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $result = $this->categoryService->update($category, $validated);

        return redirect()
            ->route('bot-owner.manage.categories', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Bot $bot, ContentCategory $category): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);
        abort_if($category->bot_id !== $bot->id, 404);

        $result = $this->categoryService->delete($category);

        return redirect()
            ->route('bot-owner.manage.categories', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reorder(Request $request, Bot $bot): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer|exists:content_categories,id',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        $result = $this->categoryService->reorder($bot, $validated['order']);

        return redirect()
            ->route('bot-owner.manage.categories', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
