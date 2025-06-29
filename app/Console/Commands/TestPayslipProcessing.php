<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Jobs\ProcessPayslip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class TestPayslipProcessing extends Command
{
    protected $signature = 'payslip:test-processing {--payslip-id= : Specific payslip ID to test}';
    protected $description = 'Test payslip processing on the latest uploaded file';

    public function handle()
    {
        $payslipId = $this->option('payslip-id');
        
        if ($payslipId) {
            $payslip = Payslip::find($payslipId);
        } else {
            $payslip = Payslip::latest()->first();
        }
        
        if (!$payslip) {
            $this->error('No payslips found');
            return 1;
        }

        $this->info("Testing payslip processing for ID: {$payslip->id}");
        $this->info("File: {$payslip->file_path}");
        
        $path = Storage::path($payslip->file_path);
        $mime = Storage::mimeType($payslip->file_path);
        
        $this->info("MIME type: {$mime}");
        $this->info("File exists: " . (file_exists($path) ? 'Yes' : 'No'));
        
        if ($mime === 'application/pdf') {
            $this->info("Testing PDF text extraction...");
            try {
                $text = (new Pdf('/usr/bin/pdftotext'))->setPdf($path)->text();
                $this->info("✅ PDF extraction successful");
                $this->info("Text length: " . strlen($text));
                
                // Show first few lines
                $lines = explode("\n", $text);
                $this->info("First 5 lines:");
                for ($i = 0; $i < min(5, count($lines)); $i++) {
                    $this->line("  " . trim($lines[$i]));
                }
                
            } catch (\Exception $e) {
                $this->error("❌ PDF extraction failed: " . $e->getMessage());
                $text = '';
            }
        } else {
            $this->warn("Non-PDF file - OCR would be needed");
            $text = '';
        }
        
        if (!empty($text)) {
            $this->info("\nTesting data extraction patterns...");
            
            // Test basic patterns
            $patterns = [
                'nama' => '/nama\s*:\s*([^:]+?)(?:\s+no\.\s*gaji|$)/i',
                'no_gaji' => '/no\.?\s*gaji\s*:?\s*([^\s\n\r]+)/i',
                'bulan' => '/bulan\s*:?\s*(\d{2}\/\d{4})/i',
                'gaji_bersih' => '/gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
                'peratus' => '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $this->info("✅ {$field}: " . trim($matches[1]));
                } else {
                    $this->line("❌ {$field}: Not found");
                }
            }
        }
        
        $this->info("\nNow dispatching actual job...");
        
        // Reset payslip status
        $payslip->update([
            'status' => 'pending',
            'processing_started_at' => null,
            'processing_completed_at' => null,
            'processing_error' => null,
            'extracted_data' => null,
        ]);
        
        // Dispatch the job
        ProcessPayslip::dispatch($payslip);
        
        $this->info("✅ Job dispatched for payslip ID: {$payslip->id}");
        $this->info("Check the logs and database for results");
        
        return 0;
    }
} 