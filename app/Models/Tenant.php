<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

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

    /**
     * Get all tags for this tenant.
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
