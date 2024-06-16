<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Tags\HasTags;

class RssChannel extends Model
{
    use HasFactory;
    use HasTags;

    public function RssChannelOrigin(): BelongsTo
    {
        return $this->belongsTo(RssChannelOrigin::class, 'origin_id');
    }
}
