<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'order_column',
    ];

    protected $casts = [
        'name' => 'array',
        'slug' => 'array',
    ];

    /**
     * Get all missions that have this tag.
     */
    public function missions(): MorphToMany
    {
        return $this->morphedByMany(Mission::class, 'taggable');
    }

    /**
     * Get all tasks that have this tag.
     */
    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    /**
     * Get all prompts that have this tag.
     */
    public function prompts(): MorphToMany
    {
        return $this->morphedByMany(Prompt::class, 'taggable');
    }

    /**
     * Get all tenants that have this tag.
     */
    public function tenants(): MorphToMany
    {
        return $this->morphedByMany(Tenant::class, 'taggable');
    }

    /**
     * Get all personnel that have this tag.
     */
    public function personnel(): MorphToMany
    {
        return $this->morphedByMany(Personnel::class, 'taggable');
    }

    /**
     * Get tag name in Persian (accessor).
     */
    public function getPersianNameAttribute(): ?string
    {
        $name = $this->attributes['name'] ?? null;
        if (!$name) {
            return null;
        }
        
        if (is_string($name)) {
            $decoded = json_decode($name, true);
            return $decoded['fa'] ?? $name;
        }
        
        if (is_array($name)) {
            return $name['fa'] ?? null;
        }
        
        return null;
    }
}
