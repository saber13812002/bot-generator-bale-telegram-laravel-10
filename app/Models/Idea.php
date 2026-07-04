<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Idea extends Model
{
    protected $fillable = [
        'endpoint_id',
        'title',
        'description',
        'submitter_name',
        'submitter_contact',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id', 'endpoint_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }
}
