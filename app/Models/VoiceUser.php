<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VoiceUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'bot_id',
        'status',
        'origin',
        'alias_name',
        'settings'
    ];

    protected $casts = [
        'settings' => 'array'
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Projects::class, 'voice_user_projects')
                    ->withPivot('status', 'settings')
                    ->withTimestamps();
    }
}
