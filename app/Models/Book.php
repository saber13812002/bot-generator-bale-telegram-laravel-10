<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'isbn',
        'shabak',
        'cover_image_file_id',
        'cover_image_file_unique_id',
        'bot_id',
        'created_by_user_id',
    ];

    /**
     * Get the bot that owns this book.
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Get the user who created this book.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'created_by_user_id');
    }

    /**
     * Get the pages for this book.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(BookPage::class);
    }
}
