<?php

namespace App\Models;

use App\Jobs\RssPostItemTranslationToMessengerJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RssPostItemTranslationQueue extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($postTranslationQueue) {
            RssPostItemTranslationToMessengerJob::dispatch($postTranslationQueue);
        });
    }

    public function postTranslation(): HasOne
    {
        return $this->hasOne(RssPostItemTranslation::class, 'id', 'rss_post_item_translation_id');
    }

}
