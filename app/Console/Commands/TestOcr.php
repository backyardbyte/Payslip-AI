<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\PdfToText\Pdf;
use thiagoalessio\TesseractOCR\TesseractOCR;

class TestOcr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ocr:test {file : Path to the file to test OCR on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OCR extraction on a payslip file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Testing OCR extraction on: {$filePath}");
        $this->newLine();

        $mime = mime_content_type($filePath);
        $text = '';

        try {
            if ($mime === 'application/pdf') {
                $this->info("Using PDF-to-text extraction...");
                $text = (new Pdf(env('PDFTOTEXT_PATH')))->setPdf($filePath)->text();
            } else {
                $this->info("Using Tesseract OCR...");
                $text = (new TesseractOCR($filePath))
                    ->lang('eng+msa')
                    ->configFile('bazaar')
                    ->run();
            }

            $this->info("Raw extracted text:");
            $this->line("=" . str_repeat("=", 80));
            $this->line($text);
            $this->line("=" . str_repeat("=", 80));
            $this->newLine();

            // Test data extraction
            $extractedData = $this->extractPayslipData($text);

            $this->info("Extracted payslip data:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Nama', $extractedData['nama'] ?? 'N/A'],
                    ['No. Gaji', $extractedData['no_gaji'] ?? 'N/A'],
                    ['Bulan', $extractedData['bulan'] ?? 'N/A'],
                    ['Gaji Pokok', $extractedData['gaji_pokok'] ? 'RM ' . number_format($extractedData['gaji_pokok'], 2) : 'N/A'],
                    ['Jumlah Pendapatan', $extractedData['jumlah_pendapatan'] ? 'RM ' . number_format($extractedData['jumlah_pendapatan'], 2) : 'N/A'],
                    ['Jumlah Potongan', $extractedData['jumlah_potongan'] ? 'RM ' . number_format($extractedData['jumlah_potongan'], 2) : 'N/A'],
                    ['Gaji Bersih', $extractedData['gaji_bersih'] ? 'RM ' . number_format($extractedData['gaji_bersih'], 2) : 'N/A'],
                    ['% Peratus Gaji Bersih', $extractedData['peratus_gaji_bersih'] ? $extractedData['peratus_gaji_bersih'] . '%' : 'N/A'],
                ]
            );

            if (!empty($extractedData['debug_patterns'])) {
                $this->newLine();
                $this->info("Debug - Patterns matched:");
                foreach ($extractedData['debug_patterns'] as $pattern) {
                    $this->line("  ✓ " . $pattern);
                }
            }

        } catch (\Exception $e) {
            $this->error("OCR extraction failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function extractPayslipData(string $text): array
    {
        $data = [
            'peratus_gaji_bersih' => null,
            'gaji_bersih' => null,
            'gaji_pokok' => null,
            'jumlah_pendapatan' => null,
            'jumlah_potongan' => null,
            'nama' => null,
            'no_gaji' => null,
            'bulan' => null,
            'debug_patterns' => []
        ];

        // Clean up text - normalize spaces and remove extra whitespace
        $cleanText = preg_replace('/\s+/', ' ', $text);
        $cleanText = trim($cleanText);

        // For multi-line matching, we also need the original text with line breaks
        $originalText = $text;

        // Extract Nama - handle the specific format where it's on the next line after "Nama"
        if (preg_match('/nama\s*:\s*([^:]+?)(?:\s+no\.\s*gaji|$)/i', $cleanText, $matches)) {
            $data['nama'] = trim($matches[1]);
            $data['debug_patterns'][] = 'nama found (inline refined)';
        } elseif (preg_match('/nama\s*:?\s*([^\n\r]+)/i', $cleanText, $matches)) {
            $data['nama'] = trim($matches[1]);
            $data['debug_patterns'][] = 'nama found (inline)';
        } elseif (preg_match('/nama\s*\n\s*:\s*([^\n\r]+)/i', $originalText, $matches)) {
            $data['nama'] = trim($matches[1]);
            $data['debug_patterns'][] = 'nama found (multiline)';
        }

        // Extract No. Gaji
        if (preg_match('/no\.?\s*gaji\s*:?\s*([^\s\n\r]+)/i', $cleanText, $matches)) {
            $data['no_gaji'] = trim($matches[1]);
            $data['debug_patterns'][] = 'no_gaji found (inline)';
        } elseif (preg_match('/no\.?\s*gaji\s*\n\s*:\s*([^\n\r]+)/i', $originalText, $matches)) {
            $data['no_gaji'] = trim($matches[1]);
            $data['debug_patterns'][] = 'no_gaji found (multiline)';
        }

        // Extract Bulan (Month/Year)
        if (preg_match('/bulan\s*:?\s*(\d{2}\/\d{4})/i', $cleanText, $matches)) {
            $data['bulan'] = trim($matches[1]);
            $data['debug_patterns'][] = 'bulan found (inline)';
        } elseif (preg_match('/bulan\s+(\d{2}\/\d{4})/i', $cleanText, $matches)) {
            $data['bulan'] = trim($matches[1]);
            $data['debug_patterns'][] = 'bulan found (adjacent)';
        }

        // Extract Gaji Pokok - look for the amount that corresponds to code 0001/Gaji Pokok
        // Pattern: Look for the amount structure where Gaji Pokok appears
        if (preg_match('/pendapatan\s+0001.*?potongan\s+amaun.*?([\d,]+\.?\d*)/is', $cleanText, $matches)) {
            $data['gaji_pokok'] = (float) str_replace(',', '', $matches[1]);
            $data['debug_patterns'][] = 'gaji_pokok found (amount structure)';
        } elseif (preg_match('/amaun.*?([\d,]+\.?\d*).*?gaji\s+pokok/is', $cleanText, $matches)) {
            $data['gaji_pokok'] = (float) str_replace(',', '', $matches[1]);
            $data['debug_patterns'][] = 'gaji_pokok found (before label)';
        } elseif (preg_match('/gaji\s+pokok\s+([0-9,]+\.?\d*)/i', $cleanText, $matches)) {
            $data['gaji_pokok'] = (float) str_replace(',', '', $matches[1]);
            $data['debug_patterns'][] = 'gaji_pokok found (adjacent)';
        } elseif (preg_match('/0001.*?gaji\s+pokok.*?([\d,]+\.?\d*)/i', $cleanText, $matches)) {
            $data['gaji_pokok'] = (float) str_replace(',', '', $matches[1]);
            $data['debug_patterns'][] = 'gaji_pokok found (with code)';
        }

        // Extract Jumlah Pendapatan
        if (preg_match('/jumlah\s+pendapatan\s*:?\s*([\d,]+\.?\d*)/i', $cleanText, $matches)) {
            $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
            $data['debug_patterns'][] = 'jumlah_pendapatan found (inline)';
        }

        // Extract values using the specific pattern in this payslip format
        // Look for the pattern: "Jumlah Potongan Gaji Bersih % Peratus Gaji Bersih" followed by amounts
        if (preg_match('/jumlah\s+potongan\s+gaji\s+bersih\s+%\s+peratus\s+gaji\s+bersih.*?:\s*:\s*:\s*([\d,]+\.?\d*)\s+([\d,]+\.?\d*)\s+([\d,]+\.?\d*)/i', $cleanText, $matches)) {
            $data['jumlah_potongan'] = (float) str_replace(',', '', $matches[1]);
            $data['gaji_bersih'] = (float) str_replace(',', '', $matches[2]);
            $data['peratus_gaji_bersih'] = (float) str_replace(',', '', $matches[3]);
            $data['debug_patterns'][] = 'all amounts found (grouped pattern)';
        } else {
            // Try individual patterns if grouped pattern fails
            
            // Extract Jumlah Potongan
            if (preg_match('/jumlah\s+potongan.*?:\s*([\d,]+\.?\d*)/i', $cleanText, $matches)) {
                $data['jumlah_potongan'] = (float) str_replace(',', '', $matches[1]);
                $data['debug_patterns'][] = 'jumlah_potongan found (individual)';
            }

            // Extract Gaji Bersih
            if (preg_match('/gaji\s+bersih.*?:\s*([\d,]+\.?\d*)/i', $cleanText, $matches)) {
                $data['gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
                $data['debug_patterns'][] = 'gaji_bersih found (individual)';
            }

            // Extract % Peratus Gaji Bersih - try multiple patterns
            $patterns = [
                '/%\s*peratus\s+gaji\s+bersih.*?:\s*([\d,]+\.?\d*)/i',
                '/peratus\s+gaji\s+bersih.*?:\s*([\d,]+\.?\d*)/i',
                // Look for the number that appears after the pattern and before parentheses
                '/%\s*peratus\s+gaji\s+bersih.*?([\d,]+\.?\d*)\s*\(/i',
                '/peratus\s+gaji\s+bersih.*?([\d,]+\.?\d*)\s*\(/i'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $cleanText, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    // Validate that this looks like a percentage (should be between 0-100)
                    if ($value >= 0 && $value <= 100) {
                        $data['peratus_gaji_bersih'] = $value;
                        $data['debug_patterns'][] = 'peratus_gaji_bersih found: ' . $pattern;
                        break;
                    }
                }
            }
        }

        // Fallback calculation: If we have jumlah_pendapatan and jumlah_potongan but no gaji_bersih,
        // calculate it: Gaji Bersih = Jumlah Pendapatan - Jumlah Potongan
        if ($data['gaji_bersih'] === null && 
            $data['jumlah_pendapatan'] !== null && 
            $data['jumlah_potongan'] !== null) {
            
            $calculatedGajiBersih = $data['jumlah_pendapatan'] - $data['jumlah_potongan'];
            
            // Validate the calculated value is reasonable
            if ($calculatedGajiBersih > 0 && $calculatedGajiBersih < 50000) {
                $data['gaji_bersih'] = round($calculatedGajiBersih, 2);
                $data['debug_patterns'][] = 'gaji_bersih calculated: ' . $data['gaji_bersih'] . ' (Pendapatan: ' . $data['jumlah_pendapatan'] . ' - Potongan: ' . $data['jumlah_potongan'] . ')';
            } else {
                $data['debug_patterns'][] = 'gaji_bersih calculation rejected: ' . $calculatedGajiBersih . ' (out of reasonable range)';
            }
        }

        return $data;
    }
} 