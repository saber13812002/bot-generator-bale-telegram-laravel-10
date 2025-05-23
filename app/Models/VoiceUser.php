<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VoiceUser extends Model
{
    use HasFactory;

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Projects::class, 'voice_user_projects', 'voice_user_id', 'project_id')
            ->withPivot('status', 'settings')
            ->withTimestamps();
    }
}
