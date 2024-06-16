<?php

namespace App\Models;

use App\Jobs\RssPostItemTranslationJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RssPostItemTranslation extends Model
{
    use HasFactory;

    protected $guarded = [];


    protected static function boot()
    {
        parent::boot();

        static::created(function ($postItemTranslation) {
            RssPostItemTranslationJob::dispatch($postItemTranslation);
        });
    }

    public function post()
    {
        return $this->hasOne(RssPostItem::class, 'id', 'rss_post_item_id');
    }
}
