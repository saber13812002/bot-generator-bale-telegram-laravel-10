<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryBook extends Model
{
    protected $fillable = [
        'bot_id',
        'title',
        'description',
        'is_active',
        'random_eligible',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'random_eligible' => 'boolean',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(LibraryGenre::class, 'library_book_genre', 'book_id', 'genre_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(LibraryBookMedia::class, 'book_id');
    }

    public function getMediaByType(string $type): ?LibraryBookMedia
    {
        return $this->media()->where('type', $type)->first();
    }
}
