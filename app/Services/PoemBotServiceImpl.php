<?php

namespace App\Services;

use App\Interfaces\Repositories\PoemLikeRepository;
use App\Interfaces\Repositories\PoemLineRepository;
use App\Interfaces\Repositories\PoemRepository;
use App\Interfaces\Repositories\PoemSuggestionRepository;
use App\Interfaces\Repositories\PoemVersionRepository;
use App\Interfaces\Services\PoemBotService;
use App\Models\Poem;
use App\Models\PoemLine;
use App\Models\PoemVersion;
use App\Models\PoemCollaboration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PoemBotServiceImpl implements PoemBotService
{
    public function __construct(
        private PoemRepository $poemRepository,
        private PoemLineRepository $lineRepository,
        private PoemVersionRepository $versionRepository,
        private PoemLikeRepository $likeRepository,
        private PoemSuggestionRepository $suggestionRepository
    ) {}

    public function createPoem(array $data, array $lines): Poem
    {
        Log::info('PoemBotService - Creating poem', ['data' => $data, 'lines_count' => count($lines)]);

        return DB::transaction(function () use ($data, $lines) {
            $poem = $this->poemRepository->create($data);

            foreach ($lines as $index => $lineContent) {
                $this->lineRepository->create([
                    'poem_id' => $poem->id,
                    'line_number' => $index + 1,
                    'content' => $lineContent,
                    'line_type' => $data['poem_type'] === 'classic' ? 'classic_line' : 'novel_sentence',
                ]);
            }

            return $poem;
        });
    }

    public function addLine(int $poemId, string $content, string $lineType, ?int $versionId = null): PoemLine
    {
        $poem = $this->poemRepository->find($poemId);
        if (!$poem) {
            throw new \Exception('Poem not found');
        }

        $maxLineNumber = $this->lineRepository->getByPoem($poemId)->max('line_number') ?? 0;

        return $this->lineRepository->create([
            'poem_id' => $poemId,
            'version_id' => $versionId,
            'line_number' => $maxLineNumber + 1,
            'content' => $content,
            'line_type' => $lineType,
        ]);
    }

    public function updateLine(int $lineId, string $content): bool
    {
        $line = $this->lineRepository->find($lineId);
        if (!$line) {
            return false;
        }

        return $this->lineRepository->update($line, ['content' => $content]);
    }

    public function deleteLine(int $lineId): bool
    {
        $line = $this->lineRepository->find($lineId);
        if (!$line) {
            return false;
        }

        return $this->lineRepository->delete($line);
    }

    public function reorderLines(int $poemId, array $lineIds): bool
    {
        return $this->lineRepository->reorderLines($poemId, $lineIds);
    }

    public function createVersion(int $poemId, int $createdBy, ?int $parentVersionId = null): PoemVersion
    {
        $latestVersion = $this->versionRepository->getLatestVersion($poemId);
        $versionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        return $this->versionRepository->create([
            'poem_id' => $poemId,
            'parent_version_id' => $parentVersionId,
            'version_number' => $versionNumber,
            'created_by' => $createdBy,
        ]);
    }

    public function likePoem(int $poemId, int $botUserId): bool
    {
        if ($this->likeRepository->exists($poemId, $botUserId)) {
            return false; // Already liked
        }

        $this->likeRepository->create([
            'poem_id' => $poemId,
            'bot_user_id' => $botUserId,
        ]);

        $poem = $this->poemRepository->find($poemId);
        if ($poem) {
            $this->poemRepository->incrementLikes($poem);
        }

        return true;
    }

    public function unlikePoem(int $poemId, int $botUserId): bool
    {
        if (!$this->likeRepository->exists($poemId, $botUserId)) {
            return false; // Not liked
        }

        $this->likeRepository->delete($poemId, $botUserId);

        $poem = $this->poemRepository->find($poemId);
        if ($poem) {
            $this->poemRepository->decrementLikes($poem);
        }

        return true;
    }

    public function isLiked(int $poemId, int $botUserId): bool
    {
        return $this->likeRepository->exists($poemId, $botUserId);
    }

    public function getUserPoems(int $botUserId, int $botMotherId): Collection
    {
        return $this->poemRepository->getByUser($botUserId, $botMotherId);
    }

    public function getPublishedPoems(int $botMotherId, ?string $orderBy = 'likes_count'): Collection
    {
        $direction = $orderBy === 'likes_count' ? 'desc' : 'desc';
        return $this->poemRepository->getPublished($botMotherId, $orderBy, $direction);
    }

    public function publishPoem(int $poemId): bool
    {
        $poem = $this->poemRepository->find($poemId);
        if (!$poem) {
            return false;
        }

        return $this->poemRepository->update($poem, ['status' => 'published']);
    }

    public function createSuggestion(int $poemId, int $suggestedBy, string $lineContent, ?int $suggestedLineNumber = null): \App\Models\PoemSuggestion
    {
        return $this->suggestionRepository->create([
            'poem_id' => $poemId,
            'suggested_by' => $suggestedBy,
            'line_content' => $lineContent,
            'suggested_line_number' => $suggestedLineNumber,
            'status' => 'pending',
        ]);
    }

    public function acceptSuggestion(int $suggestionId): bool
    {
        $suggestion = $this->suggestionRepository->find($suggestionId);
        if (!$suggestion) {
            return false;
        }

        // Add the suggested line to the poem
        $this->addLine(
            $suggestion->poem_id,
            $suggestion->line_content,
            $suggestion->poem->poem_type === 'classic' ? 'classic_line' : 'novel_sentence',
            null
        );

        return $this->suggestionRepository->updateStatus($suggestion, 'accepted');
    }

    public function rejectSuggestion(int $suggestionId): bool
    {
        $suggestion = $this->suggestionRepository->find($suggestionId);
        if (!$suggestion) {
            return false;
        }

        return $this->suggestionRepository->updateStatus($suggestion, 'rejected');
    }

    public function forkPoem(int $originalPoemId, int $forkedBy, array $newLines): Poem
    {
        $originalPoem = $this->poemRepository->find($originalPoemId);
        if (!$originalPoem) {
            throw new \Exception('Original poem not found');
        }

        return DB::transaction(function () use ($originalPoem, $forkedBy, $newLines) {
            // Create new poem
            $forkedPoem = $this->poemRepository->create([
                'bot_user_id' => $forkedBy,
                'bot_mother_id' => $originalPoem->bot_mother_id,
                'bot_id' => $originalPoem->bot_id,
                'title' => $originalPoem->title . ' (Fork)',
                'poem_type' => $originalPoem->poem_type,
                'status' => 'draft',
                'likes_count' => 0,
            ]);

            // Copy lines
            foreach ($newLines as $index => $lineContent) {
                $this->lineRepository->create([
                    'poem_id' => $forkedPoem->id,
                    'line_number' => $index + 1,
                    'content' => $lineContent,
                    'line_type' => $originalPoem->poem_type === 'classic' ? 'classic_line' : 'novel_sentence',
                ]);
            }

            // Create collaboration record
            PoemCollaboration::create([
                'original_poem_id' => $originalPoemId,
                'forked_poem_id' => $forkedPoem->id,
                'forked_by' => $forkedBy,
            ]);

            return $forkedPoem;
        });
    }
}
