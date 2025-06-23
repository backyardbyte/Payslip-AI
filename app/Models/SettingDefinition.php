<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SettingDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'display_name',
        'description',
        'category',
        'type',
        'default_value',
        'validation_rules',
        'options',
        'sort_order',
        'is_system',
        'is_encrypted',
        'is_active',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'options' => 'array',
        'sort_order' => 'integer',
        'is_system' => 'boolean',
        'is_encrypted' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the setting definition.
     */
    public function settingCategory(): BelongsTo
    {
        return $this->belongsTo(SettingCategory::class, 'category', 'name');
    }

    /**
     * Get the current system setting value.
     */
    public function systemSetting(): HasOne
    {
        return $this->hasOne(SystemSetting::class, 'key', 'key');
    }

    /**
     * Get the setting history.
     */
    public function history(): HasMany
    {
        return $this->hasMany(SettingHistory::class, 'setting_key', 'key');
    }

    /**
     * Get the current value for this setting.
     */
    public function getCurrentValue(string $environment = null): mixed
    {
        $environment = $environment ?? config('app.env', 'production');
        
        $systemSetting = $this->systemSetting()
            ->where('environment', $environment)
            ->first();

        $value = $systemSetting ? $systemSetting->value : $this->default_value;

        return $this->castValue($value);
    }

    /**
     * Cast the value to the appropriate type.
     */
    public function castValue($value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => (string) $value,
        };
    }

    /**
     * Scope for active definitions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered definitions.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    /**
     * Scope for a specific category.
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
} 