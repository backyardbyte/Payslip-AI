<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\PayslipBatch;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout;
    public $backoff;

    protected Batch $batch;

    /**
     * Create a new job instance.
     */
    public function __construct(Batch $batch)
    {
        $this->batch = $batch;
        
        // Get timeout from settings
        $settingsService = app(SettingsService::class);
        $this->timeout = $settingsService->get('advanced.batch_processing_timeout', 3600); // Default 1 hour
        
        // Calculate backoff based on configured intervals
        $baseBackoff = $settingsService->get('advanced.batch_processing_backoff', 60); // Default 60 seconds
        $this->backoff = [
            $baseBackoff,
            $baseBackoff * 5,  // 5 minutes
            $baseBackoff * 15  // 15 minutes
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting batch processing for batch {$this->batch->id}");
            
            $this->batch->update(['status' => 'processing']);

            // Get all payslips for this batch
            $payslipBatches = PayslipBatch::where('batch_id', $this->batch->id)
                ->with('payslip')
                ->get();

            $successCount = 0;
            $failedCount = 0;

            foreach ($payslipBatches as $payslipBatch) {
                try {
                    // Process each payslip
                    ProcessPayslip::dispatch($payslipBatch->payslip);
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error("Failed to dispatch payslip {$payslipBatch->payslip_id} in batch {$this->batch->id}: " . $e->getMessage());
                    $failedCount++;
                }
            }

            // Update batch status
            $this->batch->update([
                'status' => 'completed',
                'processed_at' => now(),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ]);

            Log::info("Batch {$this->batch->id} processing completed. Success: {$successCount}, Failed: {$failedCount}");

        } catch (\Exception $e) {
            Log::error("Batch processing failed for batch {$this->batch->id}: " . $e->getMessage());
            
            $this->batch->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Batch job failed for batch {$this->batch->id}: " . $exception->getMessage());
        
        $this->batch->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'batch-processing',
            'batch:' . $this->batch->id,
            'user:' . $this->batch->user_id,
        ];
    }
} 