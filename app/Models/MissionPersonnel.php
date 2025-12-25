<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionPersonnel extends Model
{
    use HasFactory;

    protected $table = 'mission_personnel';

    protected $fillable = [
        'mission_id',
        'personnel_id',
        'status',
        'result_link',
        'approval_message_id',
        'rejection_reason',
        'approved_by_chat_id',
        'approved_at',
        'rejected_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the mission for this assignment.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Get the personnel assigned to this mission.
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    /**
     * Approve the mission assignment.
     */
    public function approve(int $approvedByChatId): bool
    {
        \Illuminate\Support\Facades\Log::info('Approving mission personnel', [
            'mission_personnel_id' => $this->id,
            'mission_id' => $this->mission_id,
            'personnel_id' => $this->personnel_id,
            'approved_by_chat_id' => $approvedByChatId
        ]);

        $result = $this->update([
            'status' => 'approved',
            'approved_by_chat_id' => $approvedByChatId,
            'approved_at' => now(),
            'completed_at' => now(),
        ]);

        if ($result) {
            \Illuminate\Support\Facades\Log::info('Mission personnel approved successfully', [
                'mission_personnel_id' => $this->id,
                'status' => $this->status,
                'approved_at' => $this->approved_at?->toDateTimeString()
            ]);
        } else {
            \Illuminate\Support\Facades\Log::error('Failed to approve mission personnel', [
                'mission_personnel_id' => $this->id
            ]);
        }

        return $result;
    }

    /**
     * Reject the mission assignment.
     */
    public function reject(string $reason, int $rejectedByChatId = null): bool
    {
        \Illuminate\Support\Facades\Log::info('Rejecting mission personnel', [
            'mission_personnel_id' => $this->id,
            'mission_id' => $this->mission_id,
            'personnel_id' => $this->personnel_id,
            'rejected_by_chat_id' => $rejectedByChatId,
            'reason' => $reason
        ]);

        $result = $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by_chat_id' => $rejectedByChatId,
            'rejected_at' => now(),
        ]);

        if ($result) {
            \Illuminate\Support\Facades\Log::info('Mission personnel rejected successfully', [
                'mission_personnel_id' => $this->id,
                'status' => $this->status,
                'rejected_at' => $this->rejected_at?->toDateTimeString(),
                'rejection_reason' => $reason
            ]);
        } else {
            \Illuminate\Support\Facades\Log::error('Failed to reject mission personnel', [
                'mission_personnel_id' => $this->id
            ]);
        }

        return $result;
    }

    /**
     * Cancel the mission assignment.
     */
    public function cancel(): bool
    {
        return $this->update([
            'status' => 'cancelled',
        ]);
    }
}
