<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoemVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'poem_id',
        'parent_version_id',
        'version_number',
        'created_by',
    ];

    /**
     * Get the poem that owns this version.
     */
    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class);
    }

    /**
     * Get the parent version.
     */
    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(PoemVersion::class, 'parent_version_id');
    }

    /**
     * Get the child versions.
     */
    public function childVersions(): HasMany
    {
        return $this->hasMany(PoemVersion::class, 'parent_version_id');
    }

    /**
     * Get the user who created this version.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(BotUsers::class, 'created_by');
    }

    /**
     * Get the lines for this version.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PoemLine::class, 'version_id');
    }
}
