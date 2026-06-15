<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryBotConfig extends Model
{
    protected $fillable = [
        'bot_id',
        'reader_bot_id',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    public function readerBot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'reader_bot_id');
    }
}
