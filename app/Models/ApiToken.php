<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'plain_token', // Store plain token for first time display
        'name',
        'type',
        'tenant_id',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
        'plain_token',
    ];

    /**
     * Get the tenant that owns this token.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Generate a new API token.
     * Returns array with [ApiToken instance, plainToken string]
     */
    public static function generate(string $type = 'tenant', ?int $tenantId = null, ?string $name = null): array
    {
        $plainToken = Str::random(64);
        
        $apiToken = self::create([
            'token' => hash('sha256', $plainToken),
            'plain_token' => encrypt($plainToken), // Encrypt plain token
            'name' => $name,
            'type' => $type,
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]);
        
        return [$apiToken, $plainToken];
    }

    /**
     * Find token by plain text token.
     */
    public static function findByToken(string $plainToken): ?self
    {
        return self::where('token', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Update last used timestamp.
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
