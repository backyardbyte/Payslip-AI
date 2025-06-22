<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use Illuminate\Console\Command;

class FixGajiBersihData extends Command
{
    protected $signature = 'payslip:fix-gaji-bersih';
    protected $description = 'Fix existing payslip records by calculating missing gaji_bersih values';

    public function handle()
    {
        $this->info('Fixing payslip records with missing gaji_bersih...');
        
        // Find all payslips where we have the required data but gaji_bersih is null
        $payslips = Payslip::whereNotNull('extracted_data')
            ->where('status', 'completed')
            ->get()
            ->filter(function ($payslip) {
                $data = $payslip->extracted_data;
                return isset($data['jumlah_pendapatan']) && 
                       isset($data['jumlah_potongan']) &&
                       $data['jumlah_pendapatan'] !== null &&
                       $data['jumlah_potongan'] !== null &&
                       (!isset($data['gaji_bersih']) || $data['gaji_bersih'] === null);
            });

        if ($payslips->count() === 0) {
            $this->info('No payslips found that need fixing.');
            return 0;
        }

        $this->info("Found {$payslips->count()} payslips to fix.");
        
        $fixed = 0;
        $skipped = 0;

        foreach ($payslips as $payslip) {
            $data = $payslip->extracted_data;
            
            $calculatedGajiBersih = $data['jumlah_pendapatan'] - $data['jumlah_potongan'];
            
            // Validate the calculated value is reasonable
            if ($calculatedGajiBersih > 0 && $calculatedGajiBersih < 50000) {
                // Update the extracted_data with the calculated gaji_bersih
                $data['gaji_bersih'] = round($calculatedGajiBersih, 2);
                
                // Add debug info if it exists
                if (!isset($data['debug_info'])) {
                    $data['debug_info'] = ['extraction_patterns_found' => []];
                }
                
                if (!isset($data['debug_info']['extraction_patterns_found'])) {
                    $data['debug_info']['extraction_patterns_found'] = [];
                }
                
                $data['debug_info']['extraction_patterns_found'][] = 
                    'gaji_bersih calculated (fix): ' . $data['gaji_bersih'] . 
                    ' (Pendapatan: ' . $data['jumlah_pendapatan'] . 
                    ' - Potongan: ' . $data['jumlah_potongan'] . ')';
                
                $payslip->update(['extracted_data' => $data]);
                
                $nama = isset($data['nama']) ? $data['nama'] : 'Unknown';
                $this->line("✓ Fixed payslip ID {$payslip->id}: {$nama} - Gaji Bersih: RM " . number_format($data['gaji_bersih'], 2));
                $fixed++;
            } else {
                $this->warn("✗ Skipped payslip ID {$payslip->id}: Calculated value {$calculatedGajiBersih} is out of reasonable range");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("- Fixed: {$fixed} records");
        if ($skipped > 0) {
            $this->warn("- Skipped: {$skipped} records (out of range)");
        }
        $this->info("Done!");

        return 0;
    }
} 