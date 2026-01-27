<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PoemLineRepository;
use App\Models\PoemLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PoemLineRepositoryImpl implements PoemLineRepository
{
    public function create(array $data): PoemLine
    {
        Log::info('PoemLineRepository - Creating line', ['data' => $data]);
        
        try {
            $line = PoemLine::create($data);
            Log::info('PoemLineRepository - Line created successfully', ['id' => $line->id]);
            return $line;
        } catch (\Exception $e) {
            Log::error('PoemLineRepository - Error creating line', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?PoemLine
    {
        return PoemLine::find($id);
    }

    public function getByPoem(int $poemId): Collection
    {
        return PoemLine::where('poem_id', $poemId)
            ->orderBy('line_number', 'asc')
            ->get();
    }

    public function getByVersion(int $versionId): Collection
    {
        return PoemLine::where('version_id', $versionId)
            ->orderBy('line_number', 'asc')
            ->get();
    }

    public function update(PoemLine $line, array $data): bool
    {
        Log::info('PoemLineRepository - Updating line', ['id' => $line->id, 'data' => $data]);
        return $line->update($data);
    }

    public function delete(PoemLine $line): bool
    {
        Log::info('PoemLineRepository - Deleting line', ['id' => $line->id]);
        return $line->delete();
    }

    public function reorderLines(int $poemId, array $lineIds): bool
    {
        try {
            DB::transaction(function () use ($poemId, $lineIds) {
                foreach ($lineIds as $index => $lineId) {
                    PoemLine::where('id', $lineId)
                        ->where('poem_id', $poemId)
                        ->update(['line_number' => $index + 1]);
                }
            });
            return true;
        } catch (\Exception $e) {
            Log::error('PoemLineRepository - Error reordering lines', [
                'error' => $e->getMessage(),
                'poem_id' => $poemId
            ]);
            return false;
        }
    }
}
