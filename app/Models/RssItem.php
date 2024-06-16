<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RssItem extends Model
{
    use HasFactory;
    use \Spatie\Tags\HasTags;

    public function rssPostItems(): HasMany
    {
        return $this->hasMany(RssPostItem::class,'id','rss_item_id');
    }

    /**
     * Get the business that owns the RSS item.
     */
    public function rssBusiness()
    {
        return $this->belongsTo(RssBusiness::class, 'rss_business_id');
    }
}
