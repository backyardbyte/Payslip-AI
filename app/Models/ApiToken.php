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
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API token.
     */
    public static function generate(User $user, string $name, array $abilities = [], ?string $expiresAt = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => hash('sha256', $plainTextToken = Str::random(40)),
            'abilities' => $abilities,
            'expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
        ]);
    }

    /**
     * Check if token can perform ability.
     */
    public function can(string $ability): bool
    {
        // If no specific abilities set, inherit from user permissions
        if (empty($this->abilities)) {
            return $this->user->hasPermission($ability);
        }

        // Check if token has specific ability
        return in_array($ability, $this->abilities) || in_array('*', $this->abilities);
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Update last used timestamp.
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
} 