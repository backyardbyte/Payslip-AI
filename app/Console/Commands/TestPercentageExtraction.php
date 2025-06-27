<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPercentageExtraction extends Command
{
    protected $signature = 'test:percentage-extraction';
    protected $description = 'Test percentage extraction from various payslip formats';

    public function handle()
    {
        $this->info('Testing percentage extraction patterns...');

        // Original test case
        $sampleText = "% Peratus Gaji Bersih : 91.26";
        $this->info("Test 1 - Original inline format:");
        $this->line("Text: \"$sampleText\"");
        $this->testExtractionPatterns($sampleText);

        // Malaysian government payslip format (based on user's sample)
        $malayGovSample = "
        KERAJAAN MALAYSIA
        1104 KEMENTERIAN KERJA RAYA                                    Bulan 02/2025
        Nama            : MEOR RIDZWAN BIN ISMAIL                      Kump PTJ/PTJ     : 35 / 35232000
        No. Gaji        : 20062437                                     Pusat Pembayar   : 8027 CAWANGAN KERJA KESIHATAN
        No. K/B         : 821105-14-5133                               No Cukai FMSR    : IG234122710840 /
        K.Pkja/Sub Pkja : A / 01 Pegawai Awam

        Pendapatan                                                     Potongan
        AMAUN (RM)                                                     AMAUN (RM)
        0001  Gaji Pokok               4,572.76    2002  Cukai Pendapatan          110.50
        1052  Im Ttp Khmidmatan          700.00    6025  Angkasa                  1,248.00
        1055  Im Ttp Khidmat Awam        100.00    6026  Angkasa (Bukan PINJAMAN) 1,923.00
        1072  Bt Khas Kewangan (BKK)    500.00
        1362  B.Imb Sara Hidup           350.00

        Jumlah Pendapatan           :    5,982.76    Jumlah Potongan          :    3,277.40
        Pendapatan Bercukai         :    4,672.76    Gaji Bersih              :    2,705.36
                                                    % Peratus Gaji Bersih    :       45.22

        Bank: BIMBMYKL                              No Akaun Bank: 141530233XXXXX                  ( M/S: 1/1 )
        ";

        $this->info("\nTest 2 - Malaysian Government Payslip format:");
        $this->line("Text: [Malaysian government payslip sample]");
        $this->testExtractionPatterns($malayGovSample);

        // Another variant with different spacing
        $malayGovVariant = "
        Jumlah Pendapatan : 5,982.76 Jumlah Potongan : 3,277.40
        Pendapatan Bercukai : 4,672.76 Gaji Bersih : 2,705.36
        % Peratus Gaji Bersih : 45.22
        ";

        $this->info("\nTest 3 - Malaysian Government variant (compressed):");
        $this->line("Text: [Compressed format]");
        $this->testExtractionPatterns($malayGovVariant);

        // Edge case with different decimal places
        $edgeCase = "% Peratus Gaji Bersih : 91.2";
        $this->info("\nTest 4 - Edge case (1 decimal place):");
        $this->line("Text: \"$edgeCase\"");
        $this->testExtractionPatterns($edgeCase);

        $this->info("\n✅ Testing completed!");
    }

    private function testExtractionPatterns(string $text): void
    {
        $patterns = [
            '/%\s*Peratus\s+Gaji\s+Bersih\s*:\s*([\d,]+\.?\d*)/i' => 'Standard inline pattern',
            '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i' => 'Case insensitive pattern',
            '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{1,2})\s*$/im' => 'End of line with 1-2 decimals',
            '/gaji\s+bersih\s*:\s*[\d,]+\.\d{2}.*?%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/im' => 'After gaji bersih context',
        ];

        $found = false;
        foreach ($patterns as $pattern => $description) {
            if (preg_match($pattern, $text, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                $this->line("  ✅ $description: Found $value%");
                $found = true;
            } else {
                $this->line("  ❌ $description: No match");
            }
        }

        if (!$found) {
            $this->error("  ⚠️  No patterns matched!");
        }

        // Test side-by-side extraction
        $this->line("\n  Testing side-by-side patterns:");
        
        // Test for side-by-side jumlah pendapatan and potongan
        if (preg_match('/jumlah\s+pendapatan\s*:\s*([\d,]+\.\d{2}).*?jumlah\s+potongan\s*:\s*([\d,]+\.\d{2})/i', $text, $matches)) {
            $pendapatan = (float) str_replace(',', '', $matches[1]);
            $potongan = (float) str_replace(',', '', $matches[2]);
            $this->line("  ✅ Side-by-side pendapatan/potongan: $pendapatan / $potongan");
        } else {
            $this->line("  ❌ Side-by-side pendapatan/potongan: No match");
        }

        // Test for side-by-side pendapatan bercukai and gaji bersih
        if (preg_match('/pendapatan\s+bercukai\s*:\s*([\d,]+\.\d{2}).*?gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/i', $text, $matches)) {
            $bercukai = (float) str_replace(',', '', $matches[1]);
            $gajiBersih = (float) str_replace(',', '', $matches[2]);
            $this->line("  ✅ Side-by-side bercukai/gaji bersih: $bercukai / $gajiBersih");
        } else {
            $this->line("  ❌ Side-by-side bercukai/gaji bersih: No match");
        }
    }
} 