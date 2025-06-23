<?php

namespace App\Services;

use App\Models\SettingCategory;
use App\Models\SettingDefinition;
use App\Models\SystemSetting;
use App\Models\SettingHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    protected string $environment;
    protected int $cacheTime;

    public function __construct()
    {
        $this->environment = config('app.env', 'production');
        // Get cache duration from settings, fallback to 1 hour
        $this->cacheTime = $this->getCacheDuration();
    }

    /**
     * Get cache duration from settings.
     */
    protected function getCacheDuration(): int
    {
        try {
            $definition = SettingDefinition::where('key', 'advanced.cache_duration')->first();
            if ($definition) {
                $setting = SystemSetting::where('key', 'advanced.cache_duration')
                    ->where('environment', $this->environment)
                    ->first();
                
                $minutes = $setting ? (int) $setting->value : (int) $definition->default_value;
                return $minutes * 60; // Convert minutes to seconds
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get cache duration setting, using default', ['error' => $e->getMessage()]);
        }
        
        return 3600; // Default 1 hour fallback
    }

    /**
     * Get all settings grouped by category.
     */
    public function getAllSettings(string $environment = null): array
    {
        $environment = $environment ?? $this->environment;
        
        return Cache::remember("system_settings:{$environment}", $this->cacheTime, function () use ($environment) {
            $categories = SettingCategory::active()
                ->ordered()
                ->with(['settingDefinitions' => function ($query) {
                    $query->active()->ordered();
                }])
                ->get();

            $result = [];

            foreach ($categories as $category) {
                $categorySettings = [];
                
                foreach ($category->settingDefinitions as $definition) {
                    $categorySettings[$definition->key] = $definition->getCurrentValue($environment);
                }

                $result[$category->name] = $categorySettings;
            }

            return $result;
        });
    }

    /**
     * Get a specific setting value.
     */
    public function get(string $key, $default = null, string $environment = null): mixed
    {
        $environment = $environment ?? $this->environment;
        
        return Cache::remember("system_setting:{$key}:{$environment}", $this->cacheTime, function () use ($key, $default, $environment) {
            $definition = SettingDefinition::where('key', $key)->first();
            
            if (!$definition) {
                return $default;
            }

            return $definition->getCurrentValue($environment);
        });
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, $value, string $environment = null): bool
    {
        $environment = $environment ?? $this->environment;
        
        try {
            $definition = SettingDefinition::where('key', $key)->first();
            
            if (!$definition) {
                throw new \Exception("Setting definition not found: {$key}");
            }

            // Validate the value
            $this->validateSettingValue($definition, $value);

            // Get current value for history
            $currentSetting = SystemSetting::where('key', $key)
                ->where('environment', $environment)
                ->first();
            
            $oldValue = $currentSetting ? $currentSetting->value : null;

            // Prepare new value
            $newValue = $this->prepareValue($definition, $value);

            // Create or update the setting
            $setting = SystemSetting::updateOrCreate(
                ['key' => $key, 'environment' => $environment],
                ['value' => $newValue]
            );

            // Record history
            $this->recordHistory($key, $oldValue, $newValue, $environment);

            // Clear cache
            Cache::forget("system_setting:{$key}:{$environment}");
            Cache::forget("system_settings:{$environment}");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to set setting {$key}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Set multiple settings at once.
     */
    public function setMultiple(array $settings, string $environment = null): array
    {
        $environment = $environment ?? $this->environment;
        $results = [];
        $errors = [];

        foreach ($settings as $key => $value) {
            try {
                $this->set($key, $value, $environment);
                $results[$key] = true;
            } catch (\Exception $e) {
                $errors[$key] = $e->getMessage();
                $results[$key] = false;
            }
        }

        if (!empty($errors)) {
            Log::warning("Some settings failed to update", $errors);
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'success' => empty($errors)
        ];
    }

    /**
     * Reset setting to default value.
     */
    public function resetToDefault(string $key, string $environment = null): bool
    {
        $environment = $environment ?? $this->environment;
        
        $definition = SettingDefinition::where('key', $key)->first();
        
        if (!$definition) {
            throw new \Exception("Setting definition not found: {$key}");
        }

        return $this->set($key, $definition->default_value, $environment);
    }

    /**
     * Reset all settings to their default values.
     */
    public function resetAllToDefaults(string $environment = null): bool
    {
        $environment = $environment ?? $this->environment;
        
        try {
            $definitions = SettingDefinition::active()->get();
            
            foreach ($definitions as $definition) {
                $this->resetToDefault($definition->key, $environment);
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to reset all settings: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get setting categories with their definitions.
     */
    public function getSettingCategories(): array
    {
        return Cache::remember('setting_categories', $this->cacheTime, function () {
            return SettingCategory::active()
                ->ordered()
                ->with(['settingDefinitions' => function ($query) {
                    $query->active()->ordered();
                }])
                ->get()
                ->toArray();
        });
    }

    /**
     * Get setting history for a specific key.
     */
    public function getSettingHistory(string $key, int $limit = 50): array
    {
        return SettingHistory::forSetting($key)
            ->with('changedBy:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Validate setting value against its definition.
     */
    protected function validateSettingValue(SettingDefinition $definition, $value): void
    {
        $rules = $definition->validation_rules ?? [];
        
        // Add type-specific validation
        switch ($definition->type) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'float':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'select':
                if ($definition->options && is_array($definition->options)) {
                    $rules[] = 'in:' . implode(',', array_keys($definition->options));
                }
                break;
        }

        if (!empty($rules)) {
            $validator = Validator::make(
                ['value' => $value],
                ['value' => $rules]
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }
    }

    /**
     * Prepare value for storage.
     */
    protected function prepareValue(SettingDefinition $definition, $value): string
    {
        switch ($definition->type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
                return is_string($value) ? $value : json_encode($value);
            default:
                return (string) $value;
        }
    }

    /**
     * Record setting change in history.
     */
    protected function recordHistory(string $key, $oldValue, $newValue, string $environment): void
    {
        try {
            SettingHistory::create([
                'setting_key' => $key,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed_by' => Auth::id(),
                'environment' => $environment,
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'timestamp' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to record setting history: " . $e->getMessage());
        }
    }

    /**
     * Clear all settings cache.
     */
    public function clearCache(): void
    {
        Cache::forget("system_settings:{$this->environment}");
        Cache::forget('setting_categories');
        
        // Clear individual setting caches
        $definitions = SettingDefinition::all();
        foreach ($definitions as $definition) {
            Cache::forget("system_setting:{$definition->key}:{$this->environment}");
        }
    }

    /**
     * Export settings as JSON.
     */
    public function exportSettings(string $environment = null): array
    {
        $environment = $environment ?? $this->environment;
        
        return [
            'environment' => $environment,
            'exported_at' => now()->toISOString(),
            'settings' => $this->getAllSettings($environment)
        ];
    }

    /**
     * Import settings from array.
     */
    public function importSettings(array $settingsData, string $environment = null): array
    {
        $environment = $environment ?? $this->environment;
        $results = [];

        if (isset($settingsData['settings'])) {
            foreach ($settingsData['settings'] as $category => $settings) {
                foreach ($settings as $key => $value) {
                    try {
                        $this->set($key, $value, $environment);
                        $results[$key] = true;
                    } catch (\Exception $e) {
                        $results[$key] = false;
                        Log::error("Failed to import setting {$key}: " . $e->getMessage());
                    }
                }
            }
        }

        return $results;
    }
} 