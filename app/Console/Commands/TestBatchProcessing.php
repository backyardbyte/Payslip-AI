<?php

namespace App\Console\Commands;

use App\Models\BatchOperation;
use App\Models\Payslip;
use App\Models\User;
use App\Jobs\ProcessBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestBatchProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:test 
                            {--user-id=1 : User ID to create test batch for}
                            {--files=5 : Number of test files to create}
                            {--dry-run : Show what would be created without actually creating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test batch operation for testing the batch processing system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $fileCount = $this->option('files');
        $dryRun = $this->option('dry-run');

        // Verify user exists
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Creating test batch for user: {$user->name} ({$user->email})");
        $this->info("Number of test files: {$fileCount}");

        if ($dryRun) {
            $this->info('[DRY RUN] Would create batch with test files');
            return 0;
        }

        try {
            // Create batch operation
            $batch = BatchOperation::create([
                'user_id' => $userId,
                'name' => 'Test Batch - ' . now()->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'total_files' => $fileCount,
                'settings' => [
                    'parallel_processing' => true,
                    'max_concurrent' => 3,
                    'priority' => 'normal',
                    'processing_priority' => 10,
                ],
                'metadata' => [
                    'test_batch' => true,
                    'created_by_command' => true,
                ],
            ]);

            $this->info("Created batch: {$batch->batch_id}");

            // Create test payslip records
            for ($i = 1; $i <= $fileCount; $i++) {
                // Create a dummy file for testing
                $testContent = "Test payslip content for file {$i}\nCreated at: " . now()->toDateTimeString();
                $fileName = "test_payslip_{$i}_" . time() . ".txt";
                $filePath = "payslips/{$fileName}";
                
                Storage::put($filePath, $testContent);

                $payslip = Payslip::create([
                    'user_id' => $userId,
                    'batch_id' => $batch->batch_id,
                    'file_path' => $filePath,
                    'status' => 'queued',
                    'processing_priority' => 10,
                ]);

                $this->line("  Created test file {$i}: {$fileName}");
            }

            // Dispatch batch processing job
            ProcessBatch::dispatch($batch);

            $this->info("✓ Test batch created successfully!");
            $this->info("Batch ID: {$batch->batch_id}");
            $this->info("Files created: {$fileCount}");
            $this->info("Processing job dispatched to queue");
            
            $this->line('');
            $this->info('You can monitor the batch progress in the dashboard or using:');
            $this->line("php artisan queue:work");

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to create test batch: {$e->getMessage()}");
            return 1;
        }
    }
} 