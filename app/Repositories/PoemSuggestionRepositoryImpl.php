<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PoemSuggestionRepository;
use App\Models\PoemSuggestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PoemSuggestionRepositoryImpl implements PoemSuggestionRepository
{
    public function create(array $data): PoemSuggestion
    {
        Log::info('PoemSuggestionRepository - Creating suggestion', ['data' => $data]);
        
        try {
            $suggestion = PoemSuggestion::create($data);
            Log::info('PoemSuggestionRepository - Suggestion created successfully', ['id' => $suggestion->id]);
            return $suggestion;
        } catch (\Exception $e) {
            Log::error('PoemSuggestionRepository - Error creating suggestion', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function find(int $id): ?PoemSuggestion
    {
        return PoemSuggestion::find($id);
    }

    public function getByPoem(int $poemId, ?string $status = null): Collection
    {
        $query = PoemSuggestion::where('poem_id', $poemId);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    public function updateStatus(PoemSuggestion $suggestion, string $status): bool
    {
        Log::info('PoemSuggestionRepository - Updating suggestion status', [
            'id' => $suggestion->id,
            'status' => $status
        ]);
        return $suggestion->update(['status' => $status]);
    }
}
