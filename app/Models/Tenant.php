<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_name',
    ];

    /**
     * Get the personnel for the tenant.
     */
    public function personnel(): HasMany
    {
        return $this->hasMany(Personnel::class);
    }
}
