<?php

namespace App\Modules\BotCreation\Models;

use App\Models\Bot;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $endpoint_id
 * @property string $channel  'telegram' | 'bale' | 'web'
 * @property string $user_id  chat_id (messenger) or session_id / owner_id (web)
 * @property int|null $bot_owner_id
 * @property string $status   'in_progress' | 'completed' | 'abandoned'
 * @property int $current_step
 * @property array $collected_data  JSON of all collected answers
 * @property array $step_results    JSON of per-step validation/results
 * @property int|null $bot_id      the created bot after completion
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class BotCreationSession extends Model
{
    protected $table = 'bot_creation_sessions';

    protected $fillable = [
        'endpoint_id',
        'channel',
        'user_id',
        'bot_owner_id',
        'status',
        'current_step',
        'collected_data',
        'step_results',
        'bot_id',
        'error_message',
        'expires_at',
    ];

    protected $casts = [
        'collected_data' => 'array',
        'step_results' => 'array',
        'current_step' => 'integer',
        'bot_owner_id' => 'integer',
        'bot_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABANDONED = 'abandoned';

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id', 'endpoint_id');
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Store a single collected value.
     */
    public function setCollected(string $key, mixed $value): void
    {
        $data = $this->collected_data ?? [];
        $data[$key] = $value;
        $this->collected_data = $data;
    }

    /**
     * Get a single collected value.
     */
    public function getCollected(string $key, mixed $default = null): mixed
    {
        return ($this->collected_data ?? [])[$key] ?? $default;
    }

    /**
     * Get the total number of steps for this session's endpoint.
     */
    public function getTotalSteps(): int
    {
        // Will be hydrated from the workflow service
        return 0;
    }
}
