<?php

namespace App\Services;

use App\Models\ChannelPosterDestination;
use Illuminate\Support\Facades\DB;

class ChannelPosterService
{
    /**
     * Create destination rows for a tag with given platform data.
     * @param string $tag
     * @param array $platforms array of ['platform'=>..., 'channel_chat_id'=>..., 'channel_title'=>..., 'bot_token'=>...]
     * @return array ['success'=>bool, 'data'=>mixed, 'error'=>string]
     */
    public function createDestination(string $tag, array $platforms): array
    {
        DB::beginTransaction();
        try {
            $created = [];
            foreach ($platforms as $platform) {
                $dest = ChannelPosterDestination::create([
                    'tag' => $tag,
                    'platform' => $platform['platform'],
                    'channel_chat_id' => $platform['channel_chat_id'],
                    'channel_title' => $platform['channel_title'],
                    'bot_token' => $platform['bot_token'],
                ]);
                $created[] = $dest->toArray();
            }
            DB::commit();
            return ['success' => true, 'data' => $created];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete all destinations with the given tag.
     */
    public function deleteDestinationByTag(string $tag): bool
    {
        return ChannelPosterDestination::where('tag', $tag)->delete() > 0;
    }

    /**
     * Rename a tag.
     */
    public function renameTag(string $oldTag, string $newTag): bool
    {
        return ChannelPosterDestination::where('tag', $oldTag)->update(['tag' => $newTag]) > 0;
    }
}
