<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'channel',
        'event_type',
        'enabled',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the user that owns the notification preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get preferences for a specific user and event type.
     */
    public static function getPreferencesForEvent(int $userId, string $eventType): array
    {
        return static::where('user_id', $userId)
            ->where('event_type', $eventType)
            ->where('enabled', true)
            ->pluck('channel')
            ->toArray();
    }

    /**
     * Check if a user has enabled notifications for a specific channel and event.
     */
    public static function isEnabled(int $userId, string $channel, string $eventType): bool
    {
        return static::where('user_id', $userId)
            ->where('channel', $channel)
            ->where('event_type', $eventType)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * Set default preferences for a user.
     */
    public static function setDefaults(int $userId): void
    {
        $defaultPreferences = [
            // Batch processing events
            ['channel' => 'in_app', 'event_type' => 'batch_completed'],
            ['channel' => 'email', 'event_type' => 'batch_completed'],
            ['channel' => 'in_app', 'event_type' => 'batch_failed'],
            ['channel' => 'email', 'event_type' => 'batch_failed'],
            
            // Payslip processing events
            ['channel' => 'in_app', 'event_type' => 'payslip_processed'],
            ['channel' => 'in_app', 'event_type' => 'payslip_failed'],
            ['channel' => 'email', 'event_type' => 'payslip_failed'],
            
            // System events
            ['channel' => 'in_app', 'event_type' => 'system_maintenance'],
            ['channel' => 'email', 'event_type' => 'system_maintenance'],
            
            // Security events
            ['channel' => 'in_app', 'event_type' => 'login_alert'],
            ['channel' => 'email', 'event_type' => 'login_alert'],
        ];

        foreach ($defaultPreferences as $preference) {
            static::firstOrCreate([
                'user_id' => $userId,
                'channel' => $preference['channel'],
                'event_type' => $preference['event_type'],
            ], [
                'enabled' => true,
            ]);
        }
    }
} 