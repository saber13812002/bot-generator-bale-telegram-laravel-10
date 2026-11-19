<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LibraryGenre extends Model
{
    protected $fillable = [
        'bot_id',
        'name',
        'page',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(LibraryBook::class, 'library_book_genre', 'genre_id', 'book_id');
    }
}
