<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'chat_id',
        'type',
        'file_id',
        'file_unique_id',
        'isbn',
        'book_name',
        'book_id',
        'page_number',
        'status',
    ];

    /**
     * Get the bot that owns this draft.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the book that this draft is attached to.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
