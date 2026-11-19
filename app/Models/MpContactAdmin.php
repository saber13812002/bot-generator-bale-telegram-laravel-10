<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpContactAdmin extends Model
{
    protected $fillable = [
        'bot_id',
        'chat_id',
        'origin',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];
}
