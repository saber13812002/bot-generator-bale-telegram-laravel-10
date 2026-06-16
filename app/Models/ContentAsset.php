<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAsset extends Model
{
    protected $fillable = [
        'content_item_id',
        'type',
        'content_url',
        'telegram_file_id',
        'bale_file_id',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    public function getFileIdForOrigin(string $origin): ?string
    {
        return $origin === 'bale' ? $this->bale_file_id : $this->telegram_file_id;
    }

    public function setFileIdForOrigin(string $origin, string $fileId): void
    {
        if ($origin === 'bale') {
            $this->bale_file_id = $fileId;
        } else {
            $this->telegram_file_id = $fileId;
        }
        $this->save();
    }
}
