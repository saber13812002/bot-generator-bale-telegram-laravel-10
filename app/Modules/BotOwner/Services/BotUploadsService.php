<?php

namespace App\Modules\BotOwner\Services;

use App\Models\Bot;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentAsset;
use App\Models\ContentPendingUpload;
use App\Modules\BotOwner\Contracts\BotUploadsServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;

class BotUploadsService implements BotUploadsServiceInterface
{
    public function getPendingUploads(Bot $bot): \Illuminate\Support\Collection
    {
        return ContentPendingUpload::where('bot_id', $bot->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getCategories(Bot $bot): \Illuminate\Support\Collection
    {
        return ContentCategory::where('bot_id', $bot->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'title']);
    }

    public function approve(Bot $bot, int $uploadId, int $categoryId, BotOwner $owner): array
    {
        try {
            $upload = ContentPendingUpload::where('bot_id', $bot->id)
                ->findOrFail($uploadId);

            // Verify category belongs to this bot
            $category = ContentCategory::where('bot_id', $bot->id)
                ->findOrFail($categoryId);

            // Create a content item in the category
            $maxOrder = ContentItem::where('category_id', $categoryId)->max('queue_order') ?? 0;

            $item = ContentItem::create([
                'bot_id' => $bot->id,
                'category_id' => $categoryId,
                'title' => $upload->title ?? $upload->file_unique_id ?? 'Uploaded file',
                'queue_order' => $maxOrder + 1,
                'is_active' => true,
            ]);

            // Create asset from the upload
            ContentAsset::create([
                'content_item_id' => $item->id,
                'type' => $this->guessAssetType($upload->mime_type),
                'content_url' => null,
                'telegram_file_id' => $upload->origin === 'telegram' ? $upload->file_id : null,
                'bale_file_id' => $upload->origin === 'bale' ? $upload->file_id : null,
            ]);

            // Delete the pending upload record
            $upload->delete();

            return ['success' => true, 'message' => trans('bot-owner.uploads_approved')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.uploads_approve_failed')];
        }
    }

    public function reject(Bot $bot, int $uploadId, BotOwner $owner): array
    {
        try {
            $upload = ContentPendingUpload::where('bot_id', $bot->id)
                ->findOrFail($uploadId);

            $upload->delete();

            return ['success' => true, 'message' => trans('bot-owner.uploads_rejected')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => trans('bot-owner.uploads_reject_failed')];
        }
    }

    private function guessAssetType(?string $mimeType): string
    {
        if (!$mimeType) {
            return 'document';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'image/')) {
            return 'photo';
        }

        return 'document';
    }
}
