<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookScanMission extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'book_page_scan_id',
        'assigned_to_chat_id',
        'status',
        'approved_by_chat_id',
        'assigned_at',
        'completed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the bot that owns this mission.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the scan that this mission is for.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(BookPageScan::class, 'book_page_scan_id');
    }
}
