<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
        'is_premium',
        'is_verified',
        'is_bot',
        'language',
        'timezone',
        'notifications_enabled',
        'auto_delete_files',
        'preferred_koperasi',
        'conversation_state',
        'conversation_data',
        'last_activity_at',
        'total_payslips_processed',
        'settings',
        'is_admin',
        'is_active',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'is_verified' => 'boolean',
        'is_bot' => 'boolean',
        'notifications_enabled' => 'boolean',
        'auto_delete_files' => 'boolean',
        'preferred_koperasi' => 'array',
        'conversation_data' => 'array',
        'last_activity_at' => 'datetime',
        'settings' => 'array',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'language' => 'ms',
        'timezone' => 'Asia/Kuala_Lumpur',
        'notifications_enabled' => true,
        'auto_delete_files' => false,
        'conversation_state' => 'none',
        'conversation_data' => '{}',
        'settings' => '{}',
        'is_admin' => false,
        'is_active' => true,
    ];

    /**
     * Get the user that owns the Telegram user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payslips for the Telegram user.
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'telegram_user_id');
    }

    /**
     * Update last activity timestamp
     */
    public function updateActivity(): void
    {
        $this->update([
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * Set conversation state
     */
    public function setConversationState(string $state, array $data = []): void
    {
        $this->update([
            'conversation_state' => $state,
            'conversation_data' => $data,
        ]);
    }

    /**
     * Get conversation state
     */
    public function getConversationState(): array
    {
        return [
            'state' => $this->conversation_state,
            'data' => $this->conversation_data ?? [],
        ];
    }

    /**
     * Increment payslip counter
     */
    public function incrementPayslipCounter(): void
    {
        $this->increment('total_payslips_processed');
    }

    /**
     * Get user's preferred language
     */
    public function getLanguage(): string
    {
        return $this->language ?? 'ms';
    }

    /**
     * Set user's language
     */
    public function setLanguage(string $language): void
    {
        $this->update(['language' => $language]);
    }

    /**
     * Get user's display name
     */
    public function getDisplayName(): string
    {
        $name = trim($this->first_name . ' ' . $this->last_name);
        return $name ?: $this->username ?: "User {$this->telegram_id}";
    }

    /**
     * Check if user has notifications enabled
     */
    public function hasNotificationsEnabled(): bool
    {
        return $this->notifications_enabled;
    }

    /**
     * Toggle notifications
     */
    public function toggleNotifications(): bool
    {
        $this->update(['notifications_enabled' => !$this->notifications_enabled]);
        return $this->notifications_enabled;
    }

    /**
     * Get user setting
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set user setting
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for admin users
     */
    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    /**
     * Scope for users with notifications enabled
     */
    public function scopeWithNotifications($query)
    {
        return $query->where('notifications_enabled', true);
    }

    /**
     * Scope for recently active users
     */
    public function scopeRecentlyActive($query, int $days = 7)
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }
} 