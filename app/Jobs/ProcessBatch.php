<?php

namespace App\Jobs;

use App\Models\BatchOperation;
use App\Models\Payslip;
use App\Services\NotificationService;
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

    public $timeout = 3600; // 1 hour timeout for batch processing
    public $tries = 3;
    public $backoff = [60, 300, 900]; // Exponential backoff: 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BatchOperation $batchOperation
    ) {
        // Set queue priority based on batch settings
        $priority = $batchOperation->settings['priority'] ?? 'normal';
        $this->onQueue($priority === 'high' ? 'high' : 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting batch processing for batch: {$this->batchOperation->batch_id}");

        try {
            // Mark batch as started
            $this->batchOperation->markAsStarted();

            // Get batch settings
            $settings = $this->batchOperation->settings ?? [];
            $parallelProcessing = $settings['parallel_processing'] ?? true;
            $maxConcurrent = $settings['max_concurrent'] ?? 5;
            $processingPriority = $settings['processing_priority'] ?? 0;

            // Get all payslips in this batch that are queued
            $payslips = $this->batchOperation->payslips()
                ->where('status', 'queued')
                ->orderBy('processing_priority', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            if ($payslips->isEmpty()) {
                Log::warning("No queued payslips found for batch: {$this->batchOperation->batch_id}");
                $this->batchOperation->markAsCompleted();
                return;
            }

            Log::info("Processing {$payslips->count()} payslips in batch: {$this->batchOperation->batch_id}");

            if ($parallelProcessing) {
                $this->processParallel($payslips, $maxConcurrent);
            } else {
                $this->processSequential($payslips);
            }

            // Update final batch progress
            $this->batchOperation->updateProgress();

            // Send completion notification
            $this->sendCompletionNotification();

            Log::info("Completed batch processing for batch: {$this->batchOperation->batch_id}");

        } catch (\Exception $e) {
            Log::error("Batch processing failed for batch: {$this->batchOperation->batch_id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->batchOperation->markAsFailed($e->getMessage());
            
            // Send failure notification
            $this->sendFailureNotification($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Process payslips in parallel.
     */
    private function processParallel($payslips, int $maxConcurrent): void
    {
        $chunks = $payslips->chunk($maxConcurrent);
        
        foreach ($chunks as $chunk) {
            $jobs = [];
            
            // Dispatch jobs for this chunk
            foreach ($chunk as $payslip) {
                $job = new ProcessPayslip($payslip);
                $jobs[] = $job;
                
                // Set higher priority for batch processing
                $job->onQueue('batch');
                dispatch($job);
            }

            // Wait for chunk to complete before processing next chunk
            $this->waitForChunkCompletion($chunk);
            
            // Update batch progress after each chunk
            $this->batchOperation->updateProgress();
        }
    }

    /**
     * Process payslips sequentially.
     */
    private function processSequential($payslips): void
    {
        foreach ($payslips as $payslip) {
            try {
                // Process payslip directly (synchronously)
                $job = new ProcessPayslip($payslip);
                $job->handle();
                
                // Update batch progress after each file
                $this->batchOperation->updateProgress();
                
            } catch (\Exception $e) {
                Log::error("Failed to process payslip {$payslip->id} in batch {$this->batchOperation->batch_id}", [
                    'error' => $e->getMessage()
                ]);
                
                // Mark payslip as failed and continue
                $payslip->update([
                    'status' => 'failed',
                    'processing_error' => $e->getMessage(),
                    'processing_completed_at' => now(),
                ]);
            }
        }
    }

    /**
     * Wait for a chunk of payslips to complete processing.
     */
    private function waitForChunkCompletion($payslips): void
    {
        $maxWaitTime = 1800; // 30 minutes max wait
        $checkInterval = 10; // Check every 10 seconds
        $waitedTime = 0;

        while ($waitedTime < $maxWaitTime) {
            // Refresh payslips from database
            $payslipIds = $payslips->pluck('id');
            $updatedPayslips = Payslip::whereIn('id', $payslipIds)->get();
            
            // Check if all are completed or failed
            $pendingCount = $updatedPayslips->whereIn('status', ['queued', 'processing'])->count();
            
            if ($pendingCount === 0) {
                break; // All done
            }

            sleep($checkInterval);
            $waitedTime += $checkInterval;
        }

        if ($waitedTime >= $maxWaitTime) {
            Log::warning("Timeout waiting for chunk completion in batch: {$this->batchOperation->batch_id}");
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Batch processing job failed for batch: {$this->batchOperation->batch_id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->batchOperation->markAsFailed($exception->getMessage());
        
        // Send failure notification
        $this->sendFailureNotification($exception->getMessage());
    }

    /**
     * Send batch completion notification.
     */
    private function sendCompletionNotification(): void
    {
        try {
            $notificationService = app(NotificationService::class);
            $user = $this->batchOperation->user;
            
            if ($user) {
                $processingTime = $this->batchOperation->started_at && $this->batchOperation->completed_at
                    ? $this->batchOperation->completed_at->diffForHumans($this->batchOperation->started_at, true)
                    : 'N/A';

                $notificationService->sendBatchCompleted($user, [
                    'name' => $this->batchOperation->name,
                    'batch_id' => $this->batchOperation->batch_id,
                    'total_files' => $this->batchOperation->total_files,
                    'successful_files' => $this->batchOperation->successful_files,
                    'failed_files' => $this->batchOperation->failed_files,
                    'processing_time' => $processingTime,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send batch completion notification", [
                'batch_id' => $this->batchOperation->batch_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send batch failure notification.
     */
    private function sendFailureNotification(string $errorMessage): void
    {
        try {
            $notificationService = app(NotificationService::class);
            $user = $this->batchOperation->user;
            
            if ($user) {
                $notificationService->sendBatchFailed($user, [
                    'name' => $this->batchOperation->name,
                    'batch_id' => $this->batchOperation->batch_id,
                    'error_message' => $errorMessage,
                    'processed_files' => $this->batchOperation->processed_files,
                    'total_files' => $this->batchOperation->total_files,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send batch failure notification", [
                'batch_id' => $this->batchOperation->batch_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'batch-processing',
            'batch:' . $this->batchOperation->batch_id,
            'user:' . $this->batchOperation->user_id,
        ];
    }
} 