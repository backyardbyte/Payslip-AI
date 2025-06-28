<?php

namespace App\Jobs;

use App\Models\Koperasi;
use App\Models\Payslip;
use App\Services\SettingsService;
use App\Services\PayslipProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;


class ProcessPayslip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(public Payslip $payslip)
    {
        // Get timeout from settings
        $settingsService = app(SettingsService::class);
        $this->timeout = $settingsService->get('advanced.queue_timeout', 300);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get settings service
        $settingsService = app(SettingsService::class);
        $processingService = app(PayslipProcessingService::class);
        
        // Set memory limit from settings
        $memoryLimit = $settingsService->get('advanced.memory_limit', 512);
        ini_set('memory_limit', $memoryLimit . 'M');
        
        try {
            // Use the enhanced processing service
            $result = $processingService->processPayslip($this->payslip);
            
            // Send notifications with enhanced data
            $this->sendTelegramNotification($result['detailed_koperasi_results']);
            $this->sendWhatsAppNotification($result['detailed_koperasi_results']);

            // Update batch progress if this payslip is part of a batch
            if ($this->payslip->batch_id) {
                $batchOperation = $this->payslip->batchOperation;
                if ($batchOperation) {
                    $batchOperation->updateProgress();
                }
            }

        } catch (\Exception $e) {
            Log::error('Enhanced payslip processing failed for ID ' . $this->payslip->id, [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to legacy processing if the enhanced processing fails
            $this->handleLegacyProcessing();
        }
    }

    /**
     * Fallback legacy processing method
     */
    private function handleLegacyProcessing(): void
    {
        try {
            $this->payslip->update([
                'status' => 'processing',
                'processing_started_at' => now(),
            ]);

            $path = Storage::path($this->payslip->file_path);
            $mime = Storage::mimeType($this->payslip->file_path);
            $text = '';

            if ($mime === 'application/pdf') {
                $settingsService = app(SettingsService::class);
                $pdfToTextPath = $settingsService->get('ocr.pdftotext_path', '/usr/bin/pdftotext');
                
                try {
                    $text = (new Pdf($pdfToTextPath))->setPdf($path)->text();
                } catch (\Exception $pdfError) {
                    $text = $this->performOCR($path);
                }
            } else {
                $text = $this->performOCR($path);
            }

            $extractedData = $this->extractPayslipData($text);

            $koperasiResults = [];
            $detailedKoperasiResults = [];
            if ($extractedData['peratus_gaji_bersih'] !== null) {
                $koperasis = Koperasi::where('is_active', true)->get();
                foreach ($koperasis as $koperasi) {
                    $eligibilityCheck = $this->checkEligibility(
                        $extractedData['peratus_gaji_bersih'], 
                        $koperasi->rules,
                        $extractedData
                    );
                    
                    $koperasiResults[$koperasi->name] = $eligibilityCheck['eligible'];
                    $detailedKoperasiResults[$koperasi->name] = [
                        'eligible' => $eligibilityCheck['eligible'],
                        'reasons' => $eligibilityCheck['reasons']
                    ];
                }
            }

            $this->payslip->update([
                'status' => 'completed',
                'processing_completed_at' => now(),
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
                    'detailed_koperasi_results' => $detailedKoperasiResults,
                    'debug_info' => [
                        'text_length' => strlen($text),
                        'extraction_patterns_found' => $extractedData['debug_patterns'],
                        'processed_with' => 'legacy_fallback'
                    ]
                ],
            ]);

            $this->sendTelegramNotification($detailedKoperasiResults);
            $this->sendWhatsAppNotification($detailedKoperasiResults);

            if ($this->payslip->batch_id) {
                $batchOperation = $this->payslip->batchOperation;
                if ($batchOperation) {
                    $batchOperation->updateProgress();
                }
            }

        } catch (\Exception $e) {
            Log::error('Legacy payslip processing failed for ID ' . $this->payslip->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->payslip->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
                'processing_error' => $e->getMessage(),
                'extracted_data' => ['error' => $e->getMessage()],
            ]);

            $this->sendTelegramNotification([]);
            $this->sendWhatsAppNotification([]);

            if ($this->payslip->batch_id) {
                $batchOperation = $this->payslip->batchOperation;
                if ($batchOperation) {
                    $batchOperation->updateProgress();
                }
            }
        }
    }

    private function extractPayslipData(string $text): array
    {
        $settingsService = app(SettingsService::class);
        $minSalary = $settingsService->get('general.min_salary_amount', 100);
        $maxSalary = $settingsService->get('general.max_salary_amount', 50000);
        $minPercentage = $settingsService->get('general.min_percentage', 10);
        $maxPercentage = $settingsService->get('general.max_percentage', 100);
        
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

        // Handle side-by-side format for Malaysian government payslips
        if (preg_match('/jumlah\s+pendapatan\s*:\s*([\d,]+\.\d{2}).*?jumlah\s+potongan\s*:\s*([\d,]+\.\d{2})/i', $originalText, $matches)) {
            if ($data['jumlah_pendapatan'] === null) {
                $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                $data['debug_patterns'][] = 'jumlah_pendapatan found (side-by-side format)';
            }
            if ($data['jumlah_potongan'] === null) {
                $data['jumlah_potongan'] = (float) str_replace(',', '', $matches[2]);
                $data['debug_patterns'][] = 'jumlah_potongan found (side-by-side format)';
            }
        }

        // Handle side-by-side format for Gaji Bersih
        if (preg_match('/pendapatan\s+bercukai\s*:\s*([\d,]+\.\d{2}).*?gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/i', $originalText, $matches)) {
            if ($data['gaji_bersih'] === null) {
                $gajiBersihValue = (float) str_replace(',', '', $matches[2]);
                if ($gajiBersihValue > $minSalary && $gajiBersihValue < $maxSalary) {
                    $data['gaji_bersih'] = $gajiBersihValue;
                    $data['debug_patterns'][] = 'gaji_bersih found (side-by-side with pendapatan bercukai)';
                }
            }
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
            // Pattern for Malaysian government payslips with leading spaces
            '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{1,2})\s*$/im',
            // Pattern after gaji bersih context
            '/gaji\s+bersih\s*:\s*[\d,]+\.\d{2}.*?%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/im',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanText, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value >= $minPercentage && $value <= $maxPercentage) {
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
                                if ($percentageValue >= $minPercentage && $percentageValue <= $maxPercentage) {
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
                if ($value >= $minPercentage && $value <= $maxPercentage) {
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
                    if ($value > $minSalary && $value < $maxSalary) { // Configurable salary range
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
                if ($value > $minSalary && $value < $maxSalary) { // Configurable salary range
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
                if ($gajiBersihValue > $minSalary && $gajiBersihValue < $maxSalary) {
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
                    if ($value > $minSalary && $value < $maxSalary) {
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
                if ($value >= 0 && $value < $maxSalary) { // Configurable range for deductions
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
                                    if ($potonganValue >= 0 && $potonganValue < $maxSalary) {
                                        $data['jumlah_potongan'] = $potonganValue;
                                        $data['debug_patterns'][] = 'jumlah_potongan found (structure): ' . $potonganValue;
                                    }
                                }
                                
                                // Second number: Gaji Bersih
                                if (count($numberLines) >= 2 && $data['gaji_bersih'] === null) {
                                    $gajiBersihValue = $numberLines[1];
                                    $data['debug_patterns'][] = 'gaji_bersih attempt (structure): ' . $gajiBersihValue;
                                    if ($gajiBersihValue > $minSalary && $gajiBersihValue < $maxSalary) {
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

        // Fallback calculation: If we have jumlah_pendapatan and jumlah_potongan but no gaji_bersih,
        // calculate it: Gaji Bersih = Jumlah Pendapatan - Jumlah Potongan
        $data['debug_patterns'][] = 'Calculation check - gaji_bersih: ' . ($data['gaji_bersih'] ?? 'null') . 
                                   ', jumlah_pendapatan: ' . ($data['jumlah_pendapatan'] ?? 'null') . 
                                   ', jumlah_potongan: ' . ($data['jumlah_potongan'] ?? 'null');
        
        if ($data['gaji_bersih'] === null && 
            $data['jumlah_pendapatan'] !== null && 
            $data['jumlah_potongan'] !== null) {
            
            $calculatedGajiBersih = $data['jumlah_pendapatan'] - $data['jumlah_potongan'];
            
            // Validate the calculated value is reasonable
            if ($calculatedGajiBersih > 0 && $calculatedGajiBersih < $maxSalary) {
                $data['gaji_bersih'] = round($calculatedGajiBersih, 2);
                $data['debug_patterns'][] = 'gaji_bersih calculated: ' . $data['gaji_bersih'] . ' (Pendapatan: ' . $data['jumlah_pendapatan'] . ' - Potongan: ' . $data['jumlah_potongan'] . ')';
            } else {
                $data['debug_patterns'][] = 'gaji_bersih calculation rejected: ' . $calculatedGajiBersih . ' (out of configurable range)';
            }
        } else {
            $data['debug_patterns'][] = 'Calculation skipped - conditions not met';
        }

        return $data;
    }

    /**
     * Perform OCR using available method (OCR.space or Tesseract)
     */
    private function performOCR(string $filePath): string
    {
        $settingsService = app(SettingsService::class);
        $ocrMethod = $settingsService->get('ocr.method', env('OCR_METHOD', 'ocrspace')); // Use settings first, then env, then default
        $debugMode = $settingsService->get('advanced.enable_debug_mode', false);
        
        // Always log OCR method selection for troubleshooting
        Log::info('OCR method configuration', [
            'payslip_id' => $this->payslip->id,
            'method' => $ocrMethod,
            'file_path' => $filePath,
            'env_value' => env('OCR_METHOD'),
            'default_used' => env('OCR_METHOD') === null ? 'yes' : 'no'
        ]);
        
        // ALWAYS use OCR.space when configured, never try Tesseract
        if ($ocrMethod === 'ocrspace') {
            return $this->performOCRSpace($filePath);
        }
        
        // Only use Tesseract if explicitly set to tesseract AND the class exists
        if ($ocrMethod === 'tesseract' && class_exists('thiagoalessio\TesseractOCR\TesseractOCR')) {
            return $this->performTesseractOCR($filePath);
        }
        
        // Default to OCR.space for any other case
        Log::info('Using OCR.space as default OCR method', [
            'payslip_id' => $this->payslip->id,
            'configured_method' => $ocrMethod
        ]);
        return $this->performOCRSpace($filePath);
    }
    
    /**
     * Perform OCR using OCR.space API
     */
    private function performOCRSpace(string $filePath): string
    {
        $settingsService = app(SettingsService::class);
        $apiKey = $settingsService->get('ocr.ocrspace_api_key', env('OCRSPACE_API_KEY'));
        if (!$apiKey) {
            throw new \Exception('OCR.space API key not configured. Please configure it in settings or .env file');
        }
        
        $settingsService = app(SettingsService::class);
        $debugMode = $settingsService->get('advanced.enable_debug_mode', false);
        
        try {
            // Read file and encode to base64
            $fileData = file_get_contents($filePath);
            $base64 = base64_encode($fileData);
            
            // Determine file type
            $mimeType = mime_content_type($filePath);
            
            // Prepare OCR.space API request
            $postData = [
                'apikey' => $apiKey,
                'base64Image' => 'data:' . $mimeType . ';base64,' . $base64,
                'language' => 'eng,msa', // English and Malay
                'isOverlayRequired' => false,
                'detectOrientation' => true,
                'scale' => true,
                'OCREngine' => 2, // OCR Engine 2 is better for mixed languages
                'isTable' => true, // Better for structured documents like payslips
            ];
            
            if ($debugMode) {
                Log::info('Making OCR.space API request', [
                    'payslip_id' => $this->payslip->id,
                    'file_size' => strlen($fileData),
                    'mime_type' => $mimeType
                ]);
            }
            
            // Make API request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ocrTimeout = $settingsService->get('ocr.api_timeout', 120); // Configurable timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $ocrTimeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception('OCR.space API request failed: ' . $curlError);
            }
            
            if ($httpCode !== 200) {
                throw new \Exception('OCR.space API returned HTTP ' . $httpCode);
            }
            
            $result = json_decode($response, true);
            
            if (!$result) {
                throw new \Exception('Invalid OCR.space API response: Failed to decode JSON. Raw response: ' . substr($response, 0, 500));
            }
            
            if (!isset($result['ParsedResults'])) {
                // Log full response for debugging
                Log::error('OCR.space API response missing ParsedResults', [
                    'payslip_id' => $this->payslip->id,
                    'response' => $result
                ]);
                throw new \Exception('Invalid OCR.space API response: Missing ParsedResults. Response: ' . json_encode($result));
            }
            
            if (isset($result['ErrorMessage']) && !empty($result['ErrorMessage'])) {
                $errorMsg = is_array($result['ErrorMessage']) ? implode(', ', $result['ErrorMessage']) : $result['ErrorMessage'];
                throw new \Exception('OCR.space API error: ' . $errorMsg);
            }
            
            if (isset($result['OCRExitCode']) && $result['OCRExitCode'] != 1) {
                throw new \Exception('OCR.space API failed with exit code: ' . $result['OCRExitCode']);
            }
            
            // Extract text from all parsed results
            $extractedText = '';
            foreach ($result['ParsedResults'] as $parsedResult) {
                if (isset($parsedResult['ParsedText'])) {
                    $extractedText .= $parsedResult['ParsedText'] . "\n";
                }
            }
            
            if ($debugMode) {
                Log::info('OCR.space extraction completed', [
                    'payslip_id' => $this->payslip->id,
                    'text_length' => strlen($extractedText),
                    'file_parse_status' => $result['ParsedResults'][0]['FileParseExitCode'] ?? 'unknown'
                ]);
            }
            
            return trim($extractedText);
            
        } catch (\Exception $e) {
            Log::error('OCR.space processing failed for payslip ' . $this->payslip->id, [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            
            // NEVER fall back to Tesseract when using OCR.space
            throw new \Exception('OCR.space processing failed: ' . $e->getMessage() . '. Please check your OCR.space API key configuration.');
        }
    }
    
    /**
     * Perform OCR using local Tesseract
     */
    private function performTesseractOCR(string $filePath): string
    {
        if (!class_exists('thiagoalessio\TesseractOCR\TesseractOCR')) {
            throw new \Exception('Tesseract OCR library not available. Please set OCR_METHOD=ocrspace in your .env file to use cloud OCR.');
        }
        
        try {
            $tesseractClass = 'thiagoalessio\TesseractOCR\TesseractOCR';
            return (new $tesseractClass($filePath))
                ->lang('eng+msa') // English and Malay language support
                ->configFile('bazaar') // Better for mixed text
                ->run();
        } catch (\Exception $e) {
            throw new \Exception('Tesseract OCR failed: ' . $e->getMessage() . '. Consider using OCR.space by setting OCR_METHOD=ocrspace in your .env file.');
        }
    }

    private function checkEligibility(float $peratusGajiBersih, array $rules, array $extractedData): array
    {
        $eligible = true;
        $reasons = [];
        
        // Check maximum percentage of net salary
        if (isset($rules['max_peratus_gaji_bersih'])) {
            if ($peratusGajiBersih > $rules['max_peratus_gaji_bersih']) {
                $eligible = false;
                $reasons[] = "Peratus gaji bersih ({$peratusGajiBersih}%) melebihi had maksimum ({$rules['max_peratus_gaji_bersih']}%)";
            } else {
                $reasons[] = "Peratus gaji bersih ({$peratusGajiBersih}%) memenuhi had maksimum ({$rules['max_peratus_gaji_bersih']}%)";
            }
        }

        // Check minimum basic salary
        if (isset($rules['min_gaji_pokok']) && isset($extractedData['gaji_pokok'])) {
            if ($extractedData['gaji_pokok'] < $rules['min_gaji_pokok']) {
                $eligible = false;
                $reasons[] = "Gaji pokok (RM " . number_format($extractedData['gaji_pokok'], 2) . ") kurang daripada minimum (RM " . number_format($rules['min_gaji_pokok'], 2) . ")";
            } else {
                $reasons[] = "Gaji pokok (RM " . number_format($extractedData['gaji_pokok'], 2) . ") memenuhi minimum (RM " . number_format($rules['min_gaji_pokok'], 2) . ")";
            }
        }

        // Check minimum net salary
        if (isset($rules['min_gaji_bersih']) && isset($extractedData['gaji_bersih'])) {
            if ($extractedData['gaji_bersih'] < $rules['min_gaji_bersih']) {
                $eligible = false;
                $reasons[] = "Gaji bersih (RM " . number_format($extractedData['gaji_bersih'], 2) . ") kurang daripada minimum (RM " . number_format($rules['min_gaji_bersih'], 2) . ")";
            } else {
                $reasons[] = "Gaji bersih (RM " . number_format($extractedData['gaji_bersih'], 2) . ") memenuhi minimum (RM " . number_format($rules['min_gaji_bersih'], 2) . ")";
            }
        }

        // Check maximum age (if available)
        if (isset($rules['max_umur']) && isset($extractedData['umur'])) {
            if ($extractedData['umur'] > $rules['max_umur']) {
                $eligible = false;
                $reasons[] = "Umur ({$extractedData['umur']}) melebihi had maksimum ({$rules['max_umur']})";
            } else {
                $reasons[] = "Umur ({$extractedData['umur']}) memenuhi had maksimum ({$rules['max_umur']})";
            }
        }

        if (empty($reasons)) {
            $reasons[] = "Tiada peraturan khusus - layak secara default";
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons
        ];
    }

    /**
     * Send processing result to Telegram if payslip came from Telegram
     */
    private function sendTelegramNotification(array $detailedKoperasiResults): void
    {
        // Only send notification if payslip came from Telegram
        if ($this->payslip->source !== 'telegram' || !$this->payslip->telegram_chat_id) {
            return;
        }

        // Check if Telegram bot token is configured
        $token = config('services.telegram.bot_token');
        if (!$token) {
            Log::warning("Skipping Telegram notification for payslip {$this->payslip->id}: Bot token not configured");
            return;
        }

        try {
            // Use SimpleTelegramBotService for notifications
            $telegramService = new \App\Services\SimpleTelegramBotService();
            
            // Format eligibility results for Telegram
            $eligibilityResults = [];
            foreach ($detailedKoperasiResults as $koperasiName => $result) {
                $eligibilityResults[] = [
                    'koperasi_name' => $koperasiName,
                    'eligible' => $result['eligible'],
                    'reasons' => $result['reasons']
                ];
            }

            $telegramService->sendProcessingResult($this->payslip, $eligibilityResults);
            
            Log::info("Sent Telegram notification for payslip {$this->payslip->id} to chat {$this->payslip->telegram_chat_id}");

        } catch (\Exception $e) {
            Log::error("Failed to send Telegram notification for payslip {$this->payslip->id}: " . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp notification with results
     */
    private function sendWhatsAppNotification(array $detailedKoperasiResults): void
    {
        // Only send notification if payslip came from WhatsApp
        if ($this->payslip->source !== 'whatsapp' || !$this->payslip->whatsapp_phone) {
            return;
        }

        // Check if WhatsApp bot is configured
        $accessToken = config('services.whatsapp.access_token');
        if (!$accessToken) {
            Log::warning("Skipping WhatsApp notification for payslip {$this->payslip->id}: Access token not configured");
            return;
        }

        try {
            $whatsappService = new \App\Services\WhatsAppBotService();
            
            // Format eligibility results for WhatsApp
            $eligibilityResults = [];
            foreach ($detailedKoperasiResults as $koperasiName => $result) {
                $eligibilityResults[] = [
                    'koperasi_name' => $koperasiName,
                    'eligible' => $result['eligible'],
                    'reasons' => $result['reasons']
                ];
            }

            $whatsappService->sendProcessingResult($this->payslip, $eligibilityResults);
            
            Log::info("Sent WhatsApp notification for payslip {$this->payslip->id} to phone {$this->payslip->whatsapp_phone}");

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp notification for payslip {$this->payslip->id}: " . $e->getMessage());
        }
    }
}
