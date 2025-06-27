<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramUserAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'event_type',
        'event_data',
        'session_id',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected $attributes = [
        'event_data' => '{}',
        'metadata' => '{}',
    ];

    // Event types
    public const EVENT_MESSAGE_SENT = 'message_sent';
    public const EVENT_COMMAND_USED = 'command_used';
    public const EVENT_FILE_UPLOADED = 'file_uploaded';
    public const EVENT_BUTTON_CLICKED = 'button_clicked';
    public const EVENT_SETTINGS_CHANGED = 'settings_changed';
    public const EVENT_LANGUAGE_CHANGED = 'language_changed';
    public const EVENT_ERROR_OCCURRED = 'error_occurred';
    public const EVENT_SESSION_STARTED = 'session_started';
    public const EVENT_SESSION_ENDED = 'session_ended';

    /**
     * Get the Telegram user that owns the analytics record.
     */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * Create analytics record
     */
    public static function track(int $telegramUserId, string $eventType, array $eventData = [], array $metadata = []): self
    {
        return self::create([
            'telegram_user_id' => $telegramUserId,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Get analytics for specific event type
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Get analytics for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Get analytics for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('occurred_at', today());
    }

    /**
     * Get analytics for this week
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('occurred_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Get analytics for this month
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('occurred_at', now()->month)
                    ->whereYear('occurred_at', now()->year);
    }
} 