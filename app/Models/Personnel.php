<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Personnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'national_code',
        'phone_number',
        'tenant_id',
        'rank',
    ];

    /**
     * Get the tenant that owns the personnel.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the message queues for the personnel.
     */
    public function messageQueues()
    {
        return $this->hasMany(PersonnelMessageQueue::class);
    }
}
