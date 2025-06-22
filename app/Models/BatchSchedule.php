<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Cron\CronExpression;

class BatchSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'cron_expression',
        'settings',
        'status',
        'last_run_at',
        'next_run_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->calculateNextRun();
        });

        static::updating(function ($model) {
            if ($model->isDirty('cron_expression')) {
                $model->calculateNextRun();
            }
        });
    }

    /**
     * Get the user that owns the batch schedule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate the next run time based on cron expression.
     */
    public function calculateNextRun(): void
    {
        try {
            $cron = new CronExpression($this->cron_expression);
            $this->next_run_at = $cron->getNextRunDate();
        } catch (\Exception $e) {
            // Invalid cron expression, set to null
            $this->next_run_at = null;
        }
    }

    /**
     * Check if the schedule is due to run.
     */
    public function isDue(): bool
    {
        return $this->status === 'active' 
            && $this->next_run_at 
            && $this->next_run_at <= now();
    }

    /**
     * Mark the schedule as run.
     */
    public function markAsRun(): void
    {
        $this->update([
            'last_run_at' => now(),
        ]);
        
        $this->calculateNextRun();
        $this->save();
    }

    /**
     * Get human-readable cron description.
     */
    public function getCronDescriptionAttribute(): string
    {
        try {
            $cron = new CronExpression($this->cron_expression);
            
            // Basic descriptions for common patterns
            $descriptions = [
                '0 0 * * *' => 'Daily at midnight',
                '0 2 * * *' => 'Daily at 2:00 AM',
                '0 0 * * 0' => 'Weekly on Sunday at midnight',
                '0 0 1 * *' => 'Monthly on the 1st at midnight',
                '*/5 * * * *' => 'Every 5 minutes',
                '0 */2 * * *' => 'Every 2 hours',
            ];

            return $descriptions[$this->cron_expression] ?? 'Custom schedule';
        } catch (\Exception $e) {
            return 'Invalid schedule';
        }
    }

    /**
     * Scope for active schedules.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for due schedules.
     */
    public function scopeDue($query)
    {
        return $query->active()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    /**
     * Scope for user's schedules.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
} 