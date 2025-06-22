<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BatchOperation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'batch_id',
        'user_id',
        'name',
        'status',
        'total_files',
        'processed_files',
        'successful_files',
        'failed_files',
        'settings',
        'metadata',
        'started_at',
        'completed_at',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->batch_id)) {
                $model->batch_id = 'batch_' . Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the batch operation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payslips in this batch.
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'batch_id', 'batch_id');
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_files === 0) {
            return 0;
        }

        return round(($this->processed_files / $this->total_files) * 100, 2);
    }

    /**
     * Get the success rate percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_files === 0) {
            return 0;
        }

        return round(($this->successful_files / $this->processed_files) * 100, 2);
    }

    /**
     * Check if the batch is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    /**
     * Check if the batch is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Mark the batch as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the batch as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the batch as failed.
     */
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Update batch progress.
     */
    public function updateProgress(): void
    {
        $payslips = $this->payslips;
        
        $this->update([
            'processed_files' => $payslips->whereIn('status', ['completed', 'failed'])->count(),
            'successful_files' => $payslips->where('status', 'completed')->count(),
            'failed_files' => $payslips->where('status', 'failed')->count(),
        ]);

        // Mark as completed if all files are processed
        if ($this->processed_files >= $this->total_files && $this->status === 'processing') {
            $this->markAsCompleted();
        }
    }

    /**
     * Get estimated completion time.
     */
    public function getEstimatedCompletionAttribute(): ?string
    {
        if (!$this->started_at || $this->processed_files === 0) {
            return null;
        }

        $elapsedMinutes = $this->started_at->diffInMinutes(now());
        $avgTimePerFile = $elapsedMinutes / $this->processed_files;
        $remainingFiles = $this->total_files - $this->processed_files;
        $estimatedMinutes = $remainingFiles * $avgTimePerFile;

        return now()->addMinutes($estimatedMinutes)->format('Y-m-d H:i:s');
    }

    /**
     * Scope for active batches.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Scope for completed batches.
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'failed', 'cancelled']);
    }

    /**
     * Scope for user's batches.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
} 