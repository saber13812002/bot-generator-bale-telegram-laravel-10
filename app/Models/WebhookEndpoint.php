<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_id',
        'name',
        'route',
        'description',
        'requires_bot_mother_id',
        'requires_token',
        'requires_language',
        'supports_multiple_languages',
        'is_active',
    ];

    protected $casts = [
        'requires_bot_mother_id' => 'boolean',
        'requires_token' => 'boolean',
        'requires_language' => 'boolean',
        'supports_multiple_languages' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * رابطه با Bots (بر اساس endpoint_id که string است)
     */
    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class, 'endpoint_id', 'endpoint_id');
    }
}
