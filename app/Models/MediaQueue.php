<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_chat_id',
        'name',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MediaQueueItem::class)->orderBy('position');
    }
}
