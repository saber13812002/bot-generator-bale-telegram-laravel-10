<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpContactAdminRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'bot_id',
        'chat_id',
        'origin',
        'status',
    ];
}
