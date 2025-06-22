<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'batch_id',
        'file_path',
        'original_filename',
        'status',
        'processing_priority',
        'processing_started_at',
        'processing_completed_at',
        'processing_error',
        'extracted_data',
        'source',
        'telegram_chat_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'extracted_data' => 'array',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the payslip.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the batch operation this payslip belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function batchOperation(): BelongsTo
    {
        return $this->belongsTo(BatchOperation::class, 'batch_id', 'batch_id');
    }

    /**
     * Scope for payslips in a specific batch.
     */
    public function scopeInBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope for payslips not in any batch.
     */
    public function scopeNotInBatch($query)
    {
        return $query->whereNull('batch_id');
    }

    /**
     * Scope for high priority payslips.
     */
    public function scopeHighPriority($query)
    {
        return $query->where('processing_priority', '>', 0)->orderBy('processing_priority', 'desc');
    }
}
