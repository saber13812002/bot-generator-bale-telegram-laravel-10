<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PoemVersionRepository;
use App\Models\PoemVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PoemVersionRepositoryImpl implements PoemVersionRepository
{
    public function create(array $data): PoemVersion
    {
        Log::info('PoemVersionRepository - Creating version', ['data' => $data]);
        
        try {
            $version = PoemVersion::create($data);
            Log::info('PoemVersionRepository - Version created successfully', ['id' => $version->id]);
            return $version;
        } catch (\Exception $e) {
            Log::error('PoemVersionRepository - Error creating version', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?PoemVersion
    {
        return PoemVersion::find($id);
    }

    public function getByPoem(int $poemId): Collection
    {
        return PoemVersion::where('poem_id', $poemId)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    public function getLatestVersion(int $poemId): ?PoemVersion
    {
        return PoemVersion::where('poem_id', $poemId)
            ->orderBy('version_number', 'desc')
            ->first();
    }

    public function getByVersionNumber(int $poemId, int $versionNumber): ?PoemVersion
    {
        return PoemVersion::where('poem_id', $poemId)
            ->where('version_number', $versionNumber)
            ->first();
    }
}
