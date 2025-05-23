<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Projects extends Model
{
    use HasFactory;

    public function voiceUsers(): BelongsToMany
    {
        return $this->belongsToMany(VoiceUser::class, 'voice_user_projects', 'project_id', 'voice_user_id')
            ->withPivot('status', 'settings')
            ->withTimestamps();
    }
}
