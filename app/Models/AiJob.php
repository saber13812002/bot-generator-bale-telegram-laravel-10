<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ردیابی کسب‌وکار کارهای AI (WS-D).
 *
 * ماشین حالت:
 *   PENDING → QUEUED → PROCESSING → COMPLETED
 *                            │
 *                            ├── RETRYING (→ QUEUED بعد از backoff)
 *                            ├── DEFERRED (سیاست زمان 09–14 Tehran؛ تا flush 14:00)
 *                            ├── FAILED (dead letter بعد از max attempts)
 *                            ├── CANCELLED (لغو دستی/کاربر)
 *                            └── EXPIRED (مردن در صف یا فراتر از due window)
 *
 * جدول فنی 'jobs' (Laravel queue) فقط transport است؛ این جدول source of truth
 * وضعیت بیزینسی است و idempotency_key تکرار را در لحظه dispatch می‌بندد.
 */
class AiJob extends Model
{
    // ── States ──────────────────────────────────────────────
    public const STATE_PENDING    = 'PENDING';
    public const STATE_QUEUED     = 'QUEUED';
    public const STATE_PROCESSING = 'PROCESSING';
    public const STATE_COMPLETED  = 'COMPLETED';
    public const STATE_DEFERRED   = 'DEFERRED';
    public const STATE_RETRYING   = 'RETRYING';
    public const STATE_FAILED     = 'FAILED';
    public const STATE_CANCELLED  = 'CANCELLED';
    public const STATE_EXPIRED    = 'EXPIRED';

    public const STATES = [
        self::STATE_PENDING, self::STATE_QUEUED, self::STATE_PROCESSING,
        self::STATE_COMPLETED, self::STATE_DEFERRED, self::STATE_RETRYING,
        self::STATE_FAILED, self::STATE_CANCELLED, self::STATE_EXPIRED,
    ];

    // ── Priorities ──────────────────────────────────────────
    public const PRIORITY_PRO      = 'pro';
    public const PRIORITY_REGULAR  = 'regular';
    public const PRIORITY_DEFERRED = 'deferred';
    public const PRIORITY_LOW      = 'low';

    /** ترتیب مصرف صف (کوچک‌تر = اولویت بالاتر) */
    public const PRIORITY_ORDER = [
        self::PRIORITY_PRO      => 0,
        self::PRIORITY_REGULAR  => 1,
        self::PRIORITY_DEFERRED => 2,
        self::PRIORITY_LOW      => 3,
    ];

    protected $table = 'ai_jobs';

    protected $fillable = [
        'job_id', 'idempotency_key', 'bot_id', 'customer_id', 'user_id',
        'feature', 'priority', 'state', 'provider', 'model',
        'attempt_number', 'due_at', 'expires_at', 'started_at', 'completed_at',
        'heartbeat_at', 'locked_by', 'error_code', 'result_reference', 'payload',
    ];

    protected $casts = [
        'bot_id'         => 'integer',
        'customer_id'    => 'integer',
        'user_id'        => 'integer',
        'attempt_number' => 'integer',
        'due_at'         => 'datetime',
        'expires_at'     => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'heartbeat_at'   => 'datetime',
        'payload'        => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** کارهای قابل پردازش در حال حاضر (due گذشته + state مناسب) */
    public function scopeRunnable(Builder $query): Builder
    {
        return $query->whereIn('state', [self::STATE_QUEUED, self::STATE_RETRYING])
            ->where(function (Builder $q) {
                $q->whereNull('due_at')->orWhere('due_at', '<=', now());
            });
    }

    public function scopeByState(Builder $query, string $state): Builder
    {
        return $query->where('state', $state);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereIn('state', [
                self::STATE_PENDING, self::STATE_QUEUED,
                self::STATE_RETRYING, self::STATE_DEFERRED,
            ]);
    }

    /** PROCESSING که heartbeat آن کهنه شده (worker مرده / restart) */
    public function scopeStaleProcessing(Builder $query, int $afterMinutes): Builder
    {
        return $query->byState(self::STATE_PROCESSING)
            ->where(function (Builder $q) use ($afterMinutes) {
                $q->whereNull('heartbeat_at')
                  ->orWhereNull('started_at')
                  ->orWhere(function (Builder $q2) use ($afterMinutes) {
                      $q2->where('heartbeat_at', '<', now()->subMinutes($afterMinutes))
                         ->orWhere('started_at', '<', now()->subMinutes($afterMinutes));
                  });
            });
    }

    /**
     * انتخاب بعدی job برای processing با رعایت:
     *  - اولویت (pro > regular > deferred > low)
     *  - FIFO در داخل هر اولویت
     *  - سقف هم‌زمانی PROCESSING بر حسب bot
     */
    public function scopeNextRunnable(Builder $query, int $maxPerBot): Builder
    {
        return $query->runnable()
            ->whereNotIn('bot_id', function (Builder $sub) {
                $sub->select('bot_id')
                    ->from('ai_jobs')
                    ->where('state', self::STATE_PROCESSING)
                    ->whereNotNull('bot_id');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | State transitions
    |--------------------------------------------------------------------------
    */

    public function markQueued(): void
    {
        $this->forceFill(['state' => self::STATE_QUEUED])->save();
    }

    public function markProcessing(?string $lockedBy = null): void
    {
        $this->forceFill([
            'state'        => self::STATE_PROCESSING,
            'started_at'   => now(),
            'heartbeat_at' => now(),
            'locked_by'    => $lockedBy,
        ])->save();
    }

    public function heartbeat(): void
    {
        $this->forceFill(['heartbeat_at' => now()])->save();
    }

    public function markCompleted(?string $resultReference = null): void
    {
        $this->forceFill([
            'state'          => self::STATE_COMPLETED,
            'completed_at'   => now(),
            'result_reference' => $resultReference,
            'locked_by'      => null,
        ])->save();
    }

    public function markRetry(?\DateTimeInterface $dueAt, ?string $errorCode = null): void
    {
        $this->forceFill([
            'state'        => self::STATE_RETRYING,
            'due_at'       => $dueAt ? \Illuminate\Support\Carbon::instance($dueAt) : null,
            'error_code'   => $errorCode,
            'locked_by'    => null,
            'heartbeat_at' => null,
        ])->save();
    }

    public function markDeferred(?\DateTimeInterface $dueAt): void
    {
        $this->forceFill([
            'state'  => self::STATE_DEFERRED,
            'due_at' => $dueAt ? \Illuminate\Support\Carbon::instance($dueAt) : now(),
        ])->save();
    }

    public function markFailed(?string $errorCode, bool $requeue = false): void
    {
        $this->forceFill([
            'state'          => $requeue ? self::STATE_RETRYING : self::STATE_FAILED,
            'error_code'     => $errorCode,
            'completed_at'   => $requeue ? $this->completed_at : now(),
            'locked_by'      => null,
            'heartbeat_at'   => null,
        ])->save();
    }

    public function markCancelled(): void
    {
        $this->forceFill([
            'state'        => self::STATE_CANCELLED,
            'completed_at' => now(),
            'locked_by'    => null,
        ])->save();
    }

    public function markExpired(): void
    {
        $this->forceFill([
            'state'        => self::STATE_EXPIRED,
            'completed_at' => now(),
            'locked_by'    => null,
        ])->save();
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [
            self::STATE_COMPLETED, self::STATE_FAILED,
            self::STATE_CANCELLED, self::STATE_EXPIRED,
        ], true);
    }

    public function priorityRank(): int
    {
        return self::PRIORITY_ORDER[$this->priority] ?? self::PRIORITY_ORDER[self::PRIORITY_REGULAR];
    }

    /**
     * ساخت idempotency_key استاندارد:
     * {feature}:{botId}:{customerId or userId}:{hash(payload)}
     */
    public static function idempotencyKeyFor(string $feature, ?int $botId, ?int $customerId, ?int $userId, array $payload): string
    {
        $entity = $customerId ?? $userId ?? 0;
        $hash   = substr(sha1(json_encode($payload)), 0, 24);

        return sprintf('%s:%s:%s:%s', $feature, $botId ?? 0, $entity, $hash);
    }
}
