<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingBotResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'rating_bot_id',
        'chat_id',
        'origin',
        'item_index',
        'rating',
    ];

    public function ratingBot()
    {
        return $this->belongsTo(RatingBot::class);
    }
}

