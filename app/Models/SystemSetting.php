<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'environment',
    ];

    /**
     * Get the setting definition.
     */
    public function settingDefinition(): BelongsTo
    {
        return $this->belongsTo(SettingDefinition::class, 'key', 'key');
    }

    /**
     * Get the decrypted value if it's encrypted.
     */
    public function getDecryptedValueAttribute(): string
    {
        $definition = $this->settingDefinition;
        
        if ($definition && $definition->is_encrypted && $this->value) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Exception $e) {
                return $this->value; // Return raw value if decryption fails
            }
        }

        return $this->value;
    }

    /**
     * Set encrypted value if the setting is marked as encrypted.
     */
    public function setEncryptedValue(string $value): void
    {
        $definition = $this->settingDefinition;
        
        if ($definition && $definition->is_encrypted) {
            $this->value = Crypt::encryptString($value);
        } else {
            $this->value = $value;
        }
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Clear cache when settings are updated
        static::saved(function ($setting) {
            Cache::forget("system_setting:{$setting->key}:{$setting->environment}");
            Cache::forget("system_settings:{$setting->environment}");
        });

        static::deleted(function ($setting) {
            Cache::forget("system_setting:{$setting->key}:{$setting->environment}");
            Cache::forget("system_settings:{$setting->environment}");
        });
    }

    /**
     * Scope for specific environment.
     */
    public function scopeForEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }
} 