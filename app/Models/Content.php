<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'content_type',
        'content_url',
        'description',
        'sort_order',
    ];

    /**
     * Get the tenant that owns the content.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all missions that use this content.
     */
    public function missions(): BelongsToMany
    {
        return $this->belongsToMany(Mission::class, 'mission_contents')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Get media type attribute.
     */
    public function getMediaTypeAttribute(): string
    {
        return $this->content_type;
    }
}
