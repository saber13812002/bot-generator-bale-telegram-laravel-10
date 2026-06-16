<?php

namespace App\Interfaces\Services;

use App\Models\BotUsers;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentPendingUpload;
use App\Models\ContentUserProgress;

interface ContentQueueService
{
    public function getCategoriesForPage(int $botId, int $page): \Illuminate\Support\Collection;

    public function getMaxCategoryPage(int $botId): int;

    public function findCategory(int $categoryId, int $botId): ?ContentCategory;

    public function getNextItemForUser(BotUsers $botUser, int $categoryId, int $botId): ?ContentItem;

    public function advanceProgress(BotUsers $botUser, int $categoryId, int $botId, ContentItem $item): ContentUserProgress;

    public function getProgress(BotUsers $botUser, int $categoryId): int;

    public function appendPendingToCategory(ContentPendingUpload $pending, int $categoryId, ?string $title = null): ContentItem;

    public function maxQueueOrder(int $categoryId): int;

    public function getUserProgressList(BotUsers $botUser, int $botId, int $limit = 10): \Illuminate\Support\Collection;
}
