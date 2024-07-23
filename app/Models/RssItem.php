<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RssItem extends Model
{
    use HasFactory;
    use \Spatie\Tags\HasTags;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->last_synced_at = $model->last_synced_at ?? Carbon::now()->format('Y-m-d');
        });
    }

    protected $casts = [
        'last_synced_at' => 'datetime:Y-m-d',
    ];


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

    /**
     * Get the user's business id.
     *
     * @return Attribute
     */
//    protected function rssBusinessId(): Attribute
//    {
//        $userId = Auth()->user()->id;
//
//        if (!$userId) {
//            return 1;
//
//        }
//
//        $rssBusiness = RssBusiness::whereAdminUserId($userId);
//        if (!$rssBusiness) {
//            return 1;
//        }
//
//        return Attribute::make(
//            get: fn ($value) => $rssBusiness->admin_user_id,
//
//        );
//    }
}
