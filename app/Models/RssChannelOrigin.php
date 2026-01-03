<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RssChannelOrigin extends Model
{
    use HasFactory;


    protected $guarded = [];

    public function channels(): HasMany
    {
        return $this->hasMany(RssChannel::class, 'origin_id','id');
    }
}
