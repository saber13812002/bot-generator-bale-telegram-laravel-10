<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotOwner\Contracts\BotUploadsServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotUploadsController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
        private readonly BotUploadsServiceInterface $uploadsService,
    ) {
    }

    public function index(Bot $bot): View
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $uploads = $this->uploadsService->getPendingUploads($bot);
        $categories = $this->uploadsService->getCategories($bot);

        return view('bot-owner.manage.uploads', [
            'owner' => $owner,
            'bot' => $bot,
            'uploads' => $uploads,
            'categories' => $categories,
        ]);
    }

    public function approve(Request $request, Bot $bot, int $upload): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $validated = $request->validate([
            'category_id' => 'required|integer|exists:content_categories,id',
        ]);

        $result = $this->uploadsService->approve($bot, $upload, $validated['category_id'], $owner);

        return redirect()
            ->route('bot-owner.manage.uploads', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Bot $bot, int $upload): RedirectResponse
    {
        $owner = $this->authService->currentOwner();
        abort_if(!$bot->canBeManagedBy($owner), 403);

        $result = $this->uploadsService->reject($bot, $upload, $owner);

        return redirect()
            ->route('bot-owner.manage.uploads', $bot->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
