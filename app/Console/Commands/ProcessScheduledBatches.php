<?php

namespace App\Console\Commands;

use App\Models\BatchSchedule;
use App\Models\BatchOperation;
use App\Models\Payslip;
use App\Jobs\ProcessBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:process-scheduled 
                            {--dry-run : Show what would be processed without actually processing}
                            {--force : Force processing even if not due}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled batch operations that are due to run';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Checking for scheduled batch operations...');

        // Get due schedules
        $query = BatchSchedule::active();
        
        if (!$force) {
            $query->due();
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->info('No scheduled batch operations are due to run.');
            return 0;
        }

        $this->info("Found {$schedules->count()} scheduled batch operation(s) to process.");

        foreach ($schedules as $schedule) {
            $this->processSchedule($schedule, $dryRun);
        }

        $this->info('Scheduled batch processing completed.');
        return 0;
    }

    /**
     * Process a single schedule.
     */
    private function processSchedule(BatchSchedule $schedule, bool $dryRun): void
    {
        $this->line("Processing schedule: {$schedule->name} (ID: {$schedule->id})");

        try {
            // Get settings
            $settings = $schedule->settings;
            $batchName = $schedule->name . ' - ' . now()->format('Y-m-d H:i:s');

            // Find payslips to process based on schedule settings
            $payslips = $this->findPayslipsForSchedule($schedule);

            if ($payslips->isEmpty()) {
                $this->warn("  No payslips found for schedule: {$schedule->name}");
                return;
            }

            $this->info("  Found {$payslips->count()} payslips to process");

            if ($dryRun) {
                $this->info("  [DRY RUN] Would create batch with {$payslips->count()} files");
                return;
            }

            // Create batch operation
            $batch = BatchOperation::create([
                'user_id' => $schedule->user_id,
                'name' => $batchName,
                'status' => 'pending',
                'total_files' => $payslips->count(),
                'settings' => $settings,
                'metadata' => [
                    'scheduled' => true,
                    'schedule_id' => $schedule->id,
                    'schedule_name' => $schedule->name,
                ],
            ]);

            // Update payslips with batch_id
            $payslips->each(function ($payslip) use ($batch) {
                $payslip->update(['batch_id' => $batch->batch_id]);
            });

            // Dispatch batch processing job
            ProcessBatch::dispatch($batch);

            // Mark schedule as run
            $schedule->markAsRun();

            $this->info("  ✓ Created batch {$batch->batch_id} with {$payslips->count()} files");

            Log::info("Scheduled batch created", [
                'schedule_id' => $schedule->id,
                'schedule_name' => $schedule->name,
                'batch_id' => $batch->batch_id,
                'file_count' => $payslips->count(),
            ]);

        } catch (\Exception $e) {
            $this->error("  ✗ Failed to process schedule {$schedule->name}: {$e->getMessage()}");
            
            Log::error("Scheduled batch processing failed", [
                'schedule_id' => $schedule->id,
                'schedule_name' => $schedule->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Find payslips for a schedule based on its settings.
     */
    private function findPayslipsForSchedule(BatchSchedule $schedule)
    {
        $settings = $schedule->settings;
        $userId = $schedule->user_id;

        $query = Payslip::where('user_id', $userId)
            ->where('status', 'queued')
            ->whereNull('batch_id');

        // Apply filters based on schedule settings
        if (isset($settings['max_files'])) {
            $query->limit($settings['max_files']);
        }

        if (isset($settings['min_age_hours'])) {
            $query->where('created_at', '<=', now()->subHours($settings['min_age_hours']));
        }

        if (isset($settings['max_age_hours'])) {
            $query->where('created_at', '>=', now()->subHours($settings['max_age_hours']));
        }

        if (isset($settings['priority_threshold'])) {
            $query->where('processing_priority', '>=', $settings['priority_threshold']);
        }

        // Order by priority and age
        $query->orderBy('processing_priority', 'desc')
              ->orderBy('created_at', 'asc');

        return $query->get();
    }
} 