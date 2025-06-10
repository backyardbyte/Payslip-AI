<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPercentageExtraction extends Command
{
    protected $signature = 'test:percentage';
    protected $description = 'Test percentage extraction patterns with sample payslip text';

    public function handle()
    {
        // Sample text based on your payslip
        $sampleText = "% Peratus Gaji Bersih : 91.26";
        
        $this->info("Testing percentage extraction patterns...");
        $this->info("Sample text: " . $sampleText);
        $this->info("");

        $patterns = [
            // Exact pattern from sample: "% Peratus Gaji Bersih : 91.26"
            '/%\s*Peratus\s+Gaji\s+Bersih\s*:\s*([\d,]+\.?\d*)/i',
            '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
            
            // Without colon
            '/%\s*Peratus\s+Gaji\s+Bersih\s+([\d,]+\.?\d*)/i',
            '/%\s*peratus\s+gaji\s+bersih\s+([\d,]+\.?\d*)/i',
            
            // More flexible patterns
            '/peratus\s+gaji\s+bersih.*?:\s*([\d,]+\.?\d*)/i',
            '/peratus\s+gaji\s+bersih.*?([\d,]+\.?\d*)/i',
            
            // Look for number patterns that could be percentage
            '/%.*?([\d,]+\.?\d*)/i',
            '/peratus.*?([\d,]+\.?\d*)/i'
        ];

        foreach ($patterns as $index => $pattern) {
            $this->info("Pattern " . ($index + 1) . ": " . $pattern);
            
            if (preg_match($pattern, $sampleText, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value >= 10 && $value <= 100) {
                    $this->info("✓ MATCH FOUND: " . $value);
                } else {
                    $this->warn("✗ Value out of range: " . $value);
                }
            } else {
                $this->error("✗ No match");
            }
            $this->info("");
        }

        // Test with the exact text from your processed payslip
        $this->info("=== Testing with longer sample ===");
        $longerSample = "Nama : Norhakimi Bin Sahimi
No. Gaji : MD9011
Bulan : 10/2025
Bahagian/Jabatan/Unit : 
Kumpulan Gaji : M01 - M40
Pendapatan 0001 Gaji Pokok 3,198.61
Jumlah Pendapatan : 4,213.61
Potongan
Jumlah Potongan Gaji Bersih % Peratus Gaji Bersih
: : : :
294.36 3,919.25 91.26 (91.26)";

        $this->info("Sample text length: " . strlen($longerSample));
        
        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $longerSample, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value >= 10 && $value <= 100) {
                    $this->info("Pattern " . ($index + 1) . " MATCHED: " . $value);
                    break;
                }
            }
        }

        return 0;
    }
} 