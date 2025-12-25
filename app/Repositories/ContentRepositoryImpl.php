<?php

namespace App\Repositories;

use App\Interfaces\Repositories\ContentRepository;
use App\Models\Content;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ContentRepositoryImpl implements ContentRepository
{
    /**
     * Create a new content.
     */
    public function create(array $data): Content
    {
        Log::info('Creating content', ['data' => $data]);

        try {
            $content = Content::create($data);
            Log::info('Content created successfully', ['id' => $content->id]);
            return $content;
        } catch (\Exception $e) {
            Log::error('Error creating content', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Find a content by ID.
     */
    public function find(int $id): ?Content
    {
        return Content::find($id);
    }

    /**
     * Get contents by mission ID.
     */
    public function getByMission(int $missionId): Collection
    {
        return Content::join('mission_contents', 'contents.id', '=', 'mission_contents.content_id')
            ->where('mission_contents.mission_id', $missionId)
            ->orderBy('mission_contents.sort_order')
            ->select('contents.*')
            ->get();
    }

    /**
     * Get contents by tenant ID.
     */
    public function getByTenant(int $tenantId): Collection
    {
        return Content::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get();
    }
}

