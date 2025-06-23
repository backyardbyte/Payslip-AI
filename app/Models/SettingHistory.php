<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'old_value',
        'new_value',
        'changed_by',
        'environment',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the setting definition.
     */
    public function settingDefinition(): BelongsTo
    {
        return $this->belongsTo(SettingDefinition::class, 'setting_key', 'key');
    }

    /**
     * Get the user who made the change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope for a specific setting.
     */
    public function scopeForSetting($query, string $settingKey)
    {
        return $query->where('setting_key', $settingKey);
    }

    /**
     * Scope for a specific environment.
     */
    public function scopeForEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Scope for recent changes.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
} 