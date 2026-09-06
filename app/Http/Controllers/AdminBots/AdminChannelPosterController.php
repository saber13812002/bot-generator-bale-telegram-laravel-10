<?php

namespace App\Http\Controllers\AdminBots;

use App\Http\Controllers\Controller;
use App\Helpers\AdminHelper;
use App\Models\ChannelPosterDestination;
use App\Services\ChannelPosterService;
use Illuminate\Http\Request;

class AdminChannelPosterController extends Controller
{
    protected ChannelPosterService $service;

    public function __construct(ChannelPosterService $service)
    {
        $this->service = $service;
    }

    /**
     * List all channel poster destinations.
     */
    public function listDestinations(Request $request)
    {
        $chatId = $request->header('X-Chat-Id');
        if (!AdminHelper::isAdmin($chatId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $destinations = ChannelPosterDestination::all();
        return response()->json($destinations);
    }

    /**
     * Add a new destination.
     * Expected payload:
     *  - tag: string
     *  - platforms: array of ['platform' => 'bale|telegram|eitaa|soroush', 'channel_chat_id' => string, 'channel_title' => string, 'bot_token' => string]
     */
    public function addDestination(Request $request)
    {
        $chatId = $request->header('X-Chat-Id');
        if (!AdminHelper::isAdmin($chatId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'tag' => 'required|string|unique:channel_poster_destinations,tag',
            'platforms' => 'required|array|min:1',
            'platforms.*.platform' => 'required|in:bale,telegram,eitaa,soroush',
            'platforms.*.channel_chat_id' => 'required|string',
            'platforms.*.channel_title' => 'required|string',
            'platforms.*.bot_token' => 'required|string',
        ]);
        $result = $this->service->createDestination($validated['tag'], $validated['platforms']);
        if ($result['success']) {
            return response()->json(['message' => 'Destination added successfully', 'data' => $result['data']]);
        }
        return response()->json(['error' => $result['error']], 400);
    }

    /**
     * Delete a destination by tag.
     */
    public function deleteDestination(Request $request, $tag)
    {
        $chatId = $request->header('X-Chat-Id');
        if (!AdminHelper::isAdmin($chatId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $deleted = $this->service->deleteDestinationByTag($tag);
        if ($deleted) {
            return response()->json(['message' => "Destination '{$tag}' deleted."]);
        }
        return response()->json(['error' => "Destination '{$tag}' not found."], 404);
    }

    /**
     * Move (rename) a destination tag.
     * Expected payload: old_tag, new_tag
     */
    public function moveDestination(Request $request)
    {
        $chatId = $request->header('X-Chat-Id');
        if (!AdminHelper::isAdmin($chatId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'old_tag' => 'required|string|exists:channel_poster_destinations,tag',
            'new_tag' => 'required|string|unique:channel_poster_destinations,tag',
        ]);
        $updated = $this->service->renameTag($validated['old_tag'], $validated['new_tag']);
        if ($updated) {
            return response()->json(['message' => "Tag renamed to '{$validated['new_tag']}'."]);
        }
        return response()->json(['error' => 'Rename failed.'], 400);
    }
}
