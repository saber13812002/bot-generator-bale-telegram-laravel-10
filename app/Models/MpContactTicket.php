<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpContactTicket extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'bot_id',
        'chat_id',
        'origin',
        'tracking_code',
        'body',
        'status',
    ];

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => trans('bot.mp_contact_status_pending'),
            self::STATUS_REVIEWING => trans('bot.mp_contact_status_reviewing'),
            self::STATUS_CLOSED => trans('bot.mp_contact_status_closed'),
            default => $this->status,
        };
    }
}
