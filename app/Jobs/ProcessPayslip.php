<?php

namespace App\Jobs;

use App\Models\Koperasi;
use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;
use thiagoalessio\TesseractOCR\TesseractOCR;

class ProcessPayslip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Payslip $payslip)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->payslip->update(['status' => 'processing']);

        try {
            $path = Storage::path($this->payslip->file_path);
            $mime = Storage::mimeType($this->payslip->file_path);
            $text = '';

            if ($mime === 'application/pdf') {
                $text = (new Pdf(env('PDFTOTEXT_PATH')))->setPdf($path)->text();
            } else {
                $text = (new TesseractOCR($path))
                    ->lang('eng+msa') // Add Malay language support
                    ->configFile('bazaar') // Better for mixed text
                    ->run();
            }

            // Log extracted text for debugging
            Log::info('Extracted OCR text for payslip ' . $this->payslip->id, [
                'text_length' => strlen($text),
                'text_preview' => substr($text, 0, 500),
                'full_text' => $text
            ]);

            $extractedData = $this->extractPayslipData($text);

            $koperasiResults = [];
            if ($extractedData['peratus_gaji_bersih'] !== null) {
                $koperasis = Koperasi::where('is_active', true)->get();
                foreach ($koperasis as $koperasi) {
                    $koperasiResults[$koperasi->name] = $this->checkEligibility(
                        $extractedData['peratus_gaji_bersih'], 
                        $koperasi->rules
                    );
                }
            }

            $this->payslip->update([
                'status' => 'completed',
                'extracted_data' => [
                    'peratus_gaji_bersih' => $extractedData['peratus_gaji_bersih'],
                    'gaji_bersih' => $extractedData['gaji_bersih'],
                    'gaji_pokok' => $extractedData['gaji_pokok'],
                    'jumlah_pendapatan' => $extractedData['jumlah_pendapatan'],
                    'jumlah_potongan' => $extractedData['jumlah_potongan'],
                    'nama' => $extractedData['nama'],
                    'no_gaji' => $extractedData['no_gaji'],
                    'bulan' => $extractedData['bulan'],
                    'koperasi_results' => $koperasiResults,
                    'debug_info' => [
                        'text_length' => strlen($text),
                        'extraction_patterns_found' => $extractedData['debug_patterns']
                    ]
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Payslip processing failed for ID ' . $this->payslip->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->payslip->update([
                'status' => 'failed',
                'extracted_data' => ['error' => $e->getMessage()],
            ]);
        }
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

        // Extract values using the specific Malaysian payslip format
        // The format can be either inline like "% Peratus Gaji Bersih : 91.26"
        // Or multiline format where labels and values are separated:
        /*
        Jumlah Potongan
        Gaji Bersih  
        % Peratus Gaji Bersih
        
        :
        :
        :
        
        2,962.50
        1,963.60
        39.86
        */
        
        // First try inline patterns
        $patterns = [
            // Inline patterns from sample: "% Peratus Gaji Bersih : 91.26"
            '/%\s*Peratus\s+Gaji\s+Bersih\s*:\s*([\d,]+\.?\d*)/i',
            '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
            '/peratus\s+gaji\s+bersih.*?:\s*([\d,]+\.?\d*)/i',
            '/peratus\s+gaji\s+bersih.*?([\d,]+\.?\d*)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanText, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value >= 10 && $value <= 100) {
                    $data['peratus_gaji_bersih'] = $value;
                    $data['debug_patterns'][] = 'peratus_gaji_bersih found (inline): ' . $value;
                    break;
                }
            }
        }
        
        // If inline patterns fail, try multiline pattern
        if ($data['peratus_gaji_bersih'] === null) {
            // Look for the multiline format: find "% Peratus Gaji Bersih" then find numbers after colons
            if (preg_match('/%\s*Peratus\s+Gaji\s+Bersih/i', $originalText)) {
                // Split text into lines to analyze the structure
                $lines = preg_split('/\r\n|\r|\n/', $originalText);
                $foundPeratusLine = false;
                $colonLineIndex = -1;
                
                // Find the line with "% Peratus Gaji Bersih"
                foreach ($lines as $index => $line) {
                    if (preg_match('/%\s*Peratus\s+Gaji\s+Bersih/i', $line)) {
                        $foundPeratusLine = true;
                        
                                                 // Look for the pattern where we have colons (could be on separate lines or together)
                         for ($i = $index + 1; $i < count($lines) && $i < $index + 8; $i++) {
                             // Check if this line contains colons (either together or separate)
                             if (preg_match('/^\s*:\s*:\s*:\s*$/', $lines[$i]) || 
                                 preg_match('/^\s*:\s*$/', $lines[$i])) {
                                 $colonLineIndex = $i;
                                 // If it's a single colon, we need to find where the numbers start
                                 // Skip any additional colon lines
                                 while ($colonLineIndex < count($lines) - 1 && 
                                        (preg_match('/^\s*:\s*$/', $lines[$colonLineIndex + 1]) || 
                                         trim($lines[$colonLineIndex + 1]) === '')) {
                                     $colonLineIndex++;
                                 }
                                 break;
                             }
                         }
                        break;
                    }
                }
                
                // If we found the structure, look for numbers after the colon line
                if ($foundPeratusLine && $colonLineIndex > -1) {
                    for ($i = $colonLineIndex + 1; $i < count($lines) && $i < $colonLineIndex + 5; $i++) {
                        if (isset($lines[$i])) {
                            // Look for the third number (after Jumlah Potongan and Gaji Bersih)
                            $numberLines = [];
                            for ($j = $colonLineIndex + 1; $j < count($lines) && $j < $colonLineIndex + 10; $j++) {
                                if (isset($lines[$j]) && preg_match('/^\s*([\d,]+\.?\d*)\s*$/', trim($lines[$j]), $matches)) {
                                    $numberLines[] = (float) str_replace(',', '', $matches[1]);
                                }
                            }
                            
                            // The percentage should be the third number (index 2)
                            if (count($numberLines) >= 3) {
                                $percentageValue = $numberLines[2];
                                if ($percentageValue >= 10 && $percentageValue <= 100) {
                                    $data['peratus_gaji_bersih'] = $percentageValue;
                                    $data['debug_patterns'][] = 'peratus_gaji_bersih found (multiline): ' . $percentageValue;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // Fallback: look for any reasonable percentage value in parentheses like "(39.86)"
        if ($data['peratus_gaji_bersih'] === null) {
            if (preg_match('/\(\s*([\d,]+\.?\d*)\s*\)/', $cleanText, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value >= 10 && $value <= 100) {
                    $data['peratus_gaji_bersih'] = $value;
                    $data['debug_patterns'][] = 'peratus_gaji_bersih found (parentheses): ' . $value;
                }
            }
        }

        // Extract Gaji Bersih using the same multiline logic as percentage
        if ($data['gaji_bersih'] === null) {
            // Try inline patterns first
            $gajiBersihPatterns = [
                '/gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
                // Remove greedy patterns that pick up bank account numbers
                '/bersih\s*:\s*([\d,]+\.?\d*)/i'
            ];
            
            foreach ($gajiBersihPatterns as $pattern) {
                if (preg_match($pattern, $cleanText, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    $data['debug_patterns'][] = 'gaji_bersih attempt (inline): ' . $value . ' from pattern: ' . $pattern . ' match: ' . $matches[1];
                    if ($value > 100 && $value < 50000) { // Reasonable salary range
                        $data['gaji_bersih'] = $value;
                        $data['debug_patterns'][] = 'gaji_bersih found (inline): ' . $value;
                        break;
                    } else {
                        $data['debug_patterns'][] = 'gaji_bersih rejected (inline): ' . $value . ' (out of range)';
                    }
                }
            }
        }
        
        // If inline failed, use specific extraction targeting the exact "Gaji Bersih" line
        if ($data['gaji_bersih'] === null) {
            // Look for pattern like: "Gaji Bersih      :       3,845.31"
            if (preg_match('/gaji\s+bersih\s*:.*?([\d,]+\.?\d*)/i', $originalText, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                $data['debug_patterns'][] = 'gaji_bersih attempt (targeted): ' . $value . ' from match: ' . $matches[1];
                if ($value > 100 && $value < 50000) { // Reasonable salary range
                    $data['gaji_bersih'] = $value;
                    $data['debug_patterns'][] = 'gaji_bersih found (targeted): ' . $value;
                } else {
                    $data['debug_patterns'][] = 'gaji_bersih rejected (targeted): ' . $value . ' (out of range)';
                }
            }
        }
        
        // Final fallback: look in the three-number colon structure specifically
        if ($data['gaji_bersih'] === null) {
            // Find the pattern: "Jumlah Potongan : 368.30\nGaji Bersih : 3,845.31\n% Peratus Gaji Bersih : 91.26"
            if (preg_match('/jumlah\s+potongan\s*:\s*([\d,]+\.?\d*).*?gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/is', $originalText, $matches)) {
                $gajiBersihValue = (float) str_replace(',', '', $matches[2]);
                $data['debug_patterns'][] = 'gaji_bersih attempt (context): ' . $gajiBersihValue . ' from match: ' . $matches[2];
                if ($gajiBersihValue > 100 && $gajiBersihValue < 50000) {
                    $data['gaji_bersih'] = $gajiBersihValue;
                    $data['debug_patterns'][] = 'gaji_bersih found (context): ' . $gajiBersihValue;
                } else {
                    $data['debug_patterns'][] = 'gaji_bersih rejected (context): ' . $gajiBersihValue . ' (out of range)';
                }
            }
        }
        
        // Ultra-specific fallback: manually extract from the exact pattern we know
        if ($data['gaji_bersih'] === null) {
            // Look for the exact pattern with colons lined up
            $lines = preg_split('/\r\n|\r|\n/', $originalText);
            foreach ($lines as $line) {
                if (preg_match('/gaji\s+bersih/i', $line) && preg_match('/:\s*([\d,]+\.?\d*)/i', $line, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    $data['debug_patterns'][] = 'gaji_bersih attempt (line-by-line): ' . $value . ' from line: ' . trim($line);
                    if ($value > 100 && $value < 50000) {
                        $data['gaji_bersih'] = $value;
                        $data['debug_patterns'][] = 'gaji_bersih found (line-by-line): ' . $value;
                        break;
                    }
                }
            }
        }

        // Extract Jumlah Potongan with better targeting - avoid bank account numbers
        $potonganPatterns = [
            '/jumlah\s+potongan\s*:\s*([\d,]+\.?\d*)/i',
            // Don't use greedy patterns that could pick up bank account numbers
        ];
        
        foreach ($potonganPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                $data['debug_patterns'][] = 'jumlah_potongan attempt: ' . $value . ' from match: ' . $matches[1];
                if ($value >= 0 && $value < 50000) { // Reasonable range for deductions
                    $data['jumlah_potongan'] = $value;
                    $data['debug_patterns'][] = 'jumlah_potongan found: ' . $value;
                    break;
                } else {
                    $data['debug_patterns'][] = 'jumlah_potongan rejected: ' . $value . ' (out of range)';
                }
            }
        }
        
        // Specific extraction for the exact colon structure we see in Malaysian payslips
        if ($data['jumlah_potongan'] === null) {
            // Look for the exact pattern with three colons and three numbers below
            $lines = preg_split('/\r\n|\r|\n/', $originalText);
            for ($i = 0; $i < count($lines); $i++) {
                // Find "Jumlah Potongan" followed by "Gaji Bersih" followed by "% Peratus Gaji Bersih"
                if (preg_match('/jumlah\s+potongan/i', $lines[$i]) && 
                    isset($lines[$i+1]) && preg_match('/gaji\s+bersih/i', $lines[$i+1]) &&
                    isset($lines[$i+2]) && preg_match('/%\s*peratus\s+gaji\s+bersih/i', $lines[$i+2])) {
                    
                    // Look for the colon lines after these labels
                    for ($j = $i + 3; $j < count($lines) && $j < $i + 10; $j++) {
                        if (preg_match('/^\s*:\s*$/', $lines[$j])) {
                            // Found colon line, now look for numbers after it
                            $numberLines = [];
                            for ($k = $j + 1; $k < count($lines) && $k < $j + 5; $k++) {
                                if (preg_match('/^\s*([\d,]+\.?\d*)\s*$/', trim($lines[$k]), $matches)) {
                                    $numberLines[] = (float) str_replace(',', '', $matches[1]);
                                }
                            }
                            
                            // Extract both numbers from the structure
                            if (count($numberLines) >= 1) {
                                // First number: Jumlah Potongan
                                if ($data['jumlah_potongan'] === null) {
                                    $potonganValue = $numberLines[0];
                                    $data['debug_patterns'][] = 'jumlah_potongan attempt (structure): ' . $potonganValue;
                                    if ($potonganValue >= 0 && $potonganValue < 50000) {
                                        $data['jumlah_potongan'] = $potonganValue;
                                        $data['debug_patterns'][] = 'jumlah_potongan found (structure): ' . $potonganValue;
                                    }
                                }
                                
                                // Second number: Gaji Bersih
                                if (count($numberLines) >= 2 && $data['gaji_bersih'] === null) {
                                    $gajiBersihValue = $numberLines[1];
                                    $data['debug_patterns'][] = 'gaji_bersih attempt (structure): ' . $gajiBersihValue;
                                    if ($gajiBersihValue > 100 && $gajiBersihValue < 50000) {
                                        $data['gaji_bersih'] = $gajiBersihValue;
                                        $data['debug_patterns'][] = 'gaji_bersih found (structure): ' . $gajiBersihValue;
                                    }
                                }
                            }
                            break;
                        }
                    }
                    break;
                }
            }
        }

        return $data;
    }

    private function checkEligibility(float $peratusGajiBersih, array $rules): bool
    {
        if (isset($rules['max_peratus_gaji_bersih'])) {
            return $peratusGajiBersih <= $rules['max_peratus_gaji_bersih'];
        }

        // Default to eligible if no specific rule is found
        return true;
    }
}
