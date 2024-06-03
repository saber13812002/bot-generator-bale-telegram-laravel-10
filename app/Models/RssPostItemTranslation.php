<?php

namespace App\Models;

use App\Jobs\RssPostItemTranslationToMessengerJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RssPostItemTranslation extends Model
{
    use HasFactory;

    protected $guarded = [];


    protected static function boot()
    {
        parent::boot();

        static::created(function ($post) {
            RssPostItemTranslationToMessengerJob::dispatch($post);
        });
    }

    public function post()
    {
        return $this->belongsTo(RssPostItem::class);
    }
}
