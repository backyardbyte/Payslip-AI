<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Jobs\ProcessPayslip;
use App\Services\PayslipProcessingService;
use Illuminate\Console\Command;

class ReprocessPayslips extends Command
{
    protected $signature = 'payslips:reprocess {--all : Reprocess all payslips} {--completed : Only reprocess completed payslips}';
    protected $description = 'Re-process existing payslips with updated OCR logic';

    public function handle()
    {
        $query = Payslip::query();
        
        if ($this->option('completed')) {
            $query->where('status', 'completed');
            $this->info('Re-processing completed payslips...');
        } elseif ($this->option('all')) {
            $this->info('Re-processing all payslips...');
        } else {
            $this->info('Re-processing completed payslips (use --all to reprocess all)...');
            $query->where('status', 'completed');
        }
        
        $payslips = $query->get();
        
        if ($payslips->isEmpty()) {
            $this->info('No payslips found to reprocess.');
            return;
        }
        
        $this->info("Found {$payslips->count()} payslips to reprocess.");
        
        if (!$this->confirm('Do you want to continue?')) {
            $this->info('Cancelled.');
            return;
        }
        
        $bar = $this->output->createProgressBar($payslips->count());
        $bar->start();
        
        $processingService = app(PayslipProcessingService::class);
        
        foreach ($payslips as $payslip) {
            // Reset status to queued and clear old data
            $payslip->update([
                'status' => 'queued',
                'extracted_data' => null,
                'error_message' => null,
            ]);
            
            // Process using the new mode-aware service
            try {
                $result = $processingService->processPayslipWithMode($payslip);
                $this->line("  Payslip {$payslip->id}: " . ($result['status'] ?? 'completed'));
            } catch (\Exception $e) {
                $this->error("  Payslip {$payslip->id}: Failed - " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("Successfully processed {$payslips->count()} payslips for reprocessing.");
        $this->info('Check the queue status in your dashboard to see the progress.');
    }
} 