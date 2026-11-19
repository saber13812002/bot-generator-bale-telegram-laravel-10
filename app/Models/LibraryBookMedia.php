<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryBookMedia extends Model
{
    protected $fillable = [
        'book_id',
        'type',
        'content_url',
        'telegram_file_id',
        'bale_file_id',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }

    public function getCachedFileId(string $origin): ?string
    {
        return $origin === 'bale' ? $this->bale_file_id : $this->telegram_file_id;
    }

    public function setCachedFileId(string $origin, string $fileId): void
    {
        if ($origin === 'bale') {
            $this->bale_file_id = $fileId;
        } else {
            $this->telegram_file_id = $fileId;
        }
        $this->save();
    }
}
