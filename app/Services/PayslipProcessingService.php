<?php

namespace App\Services;

use App\Models\Payslip;
use App\Models\Koperasi;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Spatie\PdfToText\Pdf;

class PayslipProcessingService
{
    private SettingsService $settingsService;
    private array $extractionPatterns;
    private array $validationRules;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        $this->initializePatterns();
        $this->initializeValidationRules();
    }

    /**
     * Process a payslip with enhanced extraction and validation
     */
    public function processPayslip(Payslip $payslip): array
    {
        $startTime = microtime(true);
        $debugMode = $this->settingsService->get('advanced.enable_debug_mode', false);

        try {
            // Update status to processing
            $payslip->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'processing_metadata' => [
                    'start_time' => $startTime,
                    'debug_mode' => $debugMode,
                    'processor_version' => '2.0',
                ]
            ]);

            // Extract text from file
            $extractionResult = $this->extractTextFromFile($payslip);
            $text = $extractionResult['text'];

            if (empty($text)) {
                throw new \Exception('No text could be extracted from the file');
            }

            // Process and extract payslip data
            $extractedData = $this->extractPayslipDataAdvanced($text);
            
            // Validate extracted data
            $validationResult = $this->validateExtractedData($extractedData);
            
            // Check koperasi eligibility
            $eligibilityResults = $this->checkKoperasiEligibility($extractedData);

            // Calculate processing metrics
            $processingTime = microtime(true) - $startTime;
            $confidence = $this->calculateConfidenceScore($extractedData, $text);

            // Prepare final data
            $finalData = [
                'extracted_data' => $extractedData,
                'koperasi_results' => $eligibilityResults['simple'],
                'detailed_koperasi_results' => $eligibilityResults['detailed'],
                'validation_results' => $validationResult,
                'processing_metadata' => [
                    'extraction_method' => $extractionResult['method'],
                    'processing_time_seconds' => round($processingTime, 3),
                    'confidence_score' => $confidence,
                    'text_length' => strlen($text),
                    'processor_version' => '2.0',
                ],
                'quality_metrics' => [
                    'data_completeness' => $this->calculateDataCompleteness($extractedData),
                    'extraction_accuracy' => $confidence,
                    'validation_passed' => $validationResult['passed'],
                ]
            ];

            // Update payslip with results
            $payslip->update([
                'status' => 'completed',
                'processing_completed_at' => now(),
                'extracted_data' => $finalData,
                'processing_metadata' => $finalData['processing_metadata'],
            ]);

            return $finalData;

        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;
            
            Log::error('Payslip processing failed', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
                'processing_time' => $processingTime,
            ]);

            $payslip->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
                'processing_error' => $e->getMessage(),
                'processing_metadata' => [
                    'error' => $e->getMessage(),
                    'processing_time_seconds' => round($processingTime, 3),
                    'failed_at_stage' => $this->determineFailureStage($e),
                ]
            ]);

            throw $e;
        }
    }

    /**
     * Extract text from file with multiple methods
     */
    private function extractTextFromFile(Payslip $payslip): array
    {
        $filePath = Storage::path($payslip->file_path);
        $mimeType = Storage::mimeType($payslip->file_path);
        $text = '';
        $method = 'unknown';

        if ($mimeType === 'application/pdf') {
            try {
                $text = $this->extractPdfText($filePath);
                $method = 'pdftotext';
            } catch (\Exception $e) {
                $text = $this->performOCRSpace($filePath);
                $method = 'ocr_space_fallback';
            }
        } else {
            $ocrMethod = $this->settingsService->get('ocr.method', 'ocrspace');
            
            if ($ocrMethod === 'ocrspace') {
                $text = $this->performOCRSpace($filePath);
                $method = 'ocr_space';
            } else {
                $text = $this->performTesseractOCR($filePath);
                $method = 'tesseract';
            }
        }

        return [
            'text' => $text,
            'method' => $method,
            'text_length' => strlen($text)
        ];
    }

    /**
     * Extract data from the summary section at bottom of Malaysian payslips
     */
    private function extractSummarySection(string $text): array
    {
        $data = [
            'jumlah_pendapatan' => null,
            'jumlah_potongan' => null,
            'gaji_bersih' => null,
            'peratus_gaji_bersih' => null
        ];

        // Look for the summary section pattern common in Malaysian payslips
        $lines = explode("\n", $text);
        
        // Handle the grouped summary format: 
        // Field names listed together, then colons together, then values together
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Look for the grouped summary pattern starting with "Jumlah Potongan"
            if (preg_match('/^jumlah\s+potongan\s*$/i', $line)) {
                // Check if next lines contain "Gaji Bersih" and "% Peratus Gaji Bersih"
                $nextLine1 = isset($lines[$i+1]) ? trim($lines[$i+1]) : '';
                $nextLine2 = isset($lines[$i+2]) ? trim($lines[$i+2]) : '';
                
                if (preg_match('/^gaji\s+bersih\s*$/i', $nextLine1) && 
                    preg_match('/^%\s*peratus\s+gaji\s+bersih\s*$/i', $nextLine2)) {
                    
                    // Found the grouped format, now look for the colons and values
                    for ($j = $i + 3; $j < count($lines) && $j < $i + 10; $j++) {
                        $colonLine = trim($lines[$j]);
                        
                        // Look for the line with multiple colons
                        if (preg_match('/^\s*:\s*:\s*:\s*$/', $colonLine) || 
                            (preg_match('/^\s*:\s*$/', $colonLine) && 
                             isset($lines[$j+1]) && preg_match('/^\s*:\s*$/', trim($lines[$j+1])) &&
                             isset($lines[$j+2]) && preg_match('/^\s*:\s*$/', trim($lines[$j+2])))) {
                            
                            // Find the values after the colons
                            $valueStartIndex = $j + 1;
                            if (preg_match('/^\s*:\s*:\s*:\s*$/', $colonLine)) {
                                // Single line with three colons
                                $valueStartIndex = $j + 1;
                            } else {
                                // Multiple lines with single colons
                                $valueStartIndex = $j + 3;
                            }
                            
                            $values = [];
                            for ($k = $valueStartIndex; $k < count($lines) && $k < $valueStartIndex + 5; $k++) {
                                $valueLine = trim($lines[$k]);
                                if (preg_match('/^([\d,]+\.\d{2})\s*$/', $valueLine, $matches)) {
                                    $values[] = (float) str_replace(',', '', $matches[1]);
                                }
                            }
                            
                            // Assign values in order: Jumlah Potongan, Gaji Bersih, Peratus Gaji Bersih
                            if (count($values) >= 1) {
                                $data['jumlah_potongan'] = $values[0];
                            }
                            if (count($values) >= 2) {
                                $data['gaji_bersih'] = $values[1];
                            }
                            if (count($values) >= 3) {
                                $data['peratus_gaji_bersih'] = $values[2];
                            }
                            
                            break;
                        }
                    }
                }
            }
            
            // Handle individual "Jumlah Pendapatan" in its own section
            if (preg_match('/^jumlah\s+pendapatan\s*$/i', $line) && isset($lines[$i+1])) {
                $nextLine = trim($lines[$i+1]);
                if (preg_match('/^\s*:\s*$/', $nextLine) && isset($lines[$i+2])) {
                    $valueLine = trim($lines[$i+2]);
                    if (preg_match('/^([\d,]+\.\d{2})\s*$/', $valueLine, $matches)) {
                        $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                    }
                }
                // Also check if the value is directly on the next line with colon
                elseif (preg_match('/^\s*:\s*([\d,]+\.\d{2})\s*$/', $nextLine, $matches)) {
                    $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                }
            }
            
            // Handle multiline format: "Jumlah Pendapatan" followed by ": 5,982.76"
            if (preg_match('/^jumlah\s+pendapatan\s*$/i', $line) && isset($lines[$i+1])) {
                $nextLine = trim($lines[$i+1]);
                if (preg_match('/^\s*:\s*([\d,]+\.\d{2})\s*$/', $nextLine, $matches)) {
                    $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                    continue;
                }
            }
            
            // Handle multiline format: "Jumlah Potongan" followed by ": 3,277.40"  
            if (preg_match('/^jumlah\s+potongan\s*$/i', $line) && isset($lines[$i+1])) {
                $nextLine = trim($lines[$i+1]);
                if (preg_match('/^\s*:\s*([\d,]+\.\d{2})\s*$/', $nextLine, $matches)) {
                    $data['jumlah_potongan'] = (float) str_replace(',', '', $matches[1]);
                    continue;
                }
            }
            
            // Handle multiline format: "Gaji Bersih" followed by ": 2,705.36"
            if (preg_match('/^gaji\s+bersih\s*$/i', $line) && isset($lines[$i+1])) {
                $nextLine = trim($lines[$i+1]);
                if (preg_match('/^\s*:\s*([\d,]+\.\d{2})\s*$/', $nextLine, $matches)) {
                    $data['gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
                    continue;
                }
            }
            
            // Handle multiline format: "% Peratus Gaji Bersih" followed by ": 45.22"
            if (preg_match('/^%\s*peratus\s+gaji\s+bersih\s*$/i', $line) && isset($lines[$i+1])) {
                $nextLine = trim($lines[$i+1]);
                if (preg_match('/^\s*:\s*([\d,]+\.\d{2})\s*$/', $nextLine, $matches)) {
                    $data['peratus_gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
                    continue;
                }
            }
            
            // Handle side-by-side format like: "Jumlah Pendapatan : 5,982.76 Jumlah Potongan : 3,277.40"
            if (preg_match('/jumlah\s+pendapatan\s*:\s*([\d,]+\.\d{2}).*?jumlah\s+potongan\s*:\s*([\d,]+\.\d{2})/i', $line, $matches)) {
                $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                $data['jumlah_potongan'] = (float) str_replace(',', '', $matches[2]);
                continue;
            }
            
            // Handle side-by-side format like: "Pendapatan Bercukai : 4,672.76 Gaji Bersih : 2,705.36"
            if (preg_match('/pendapatan\s+bercukai\s*:\s*([\d,]+\.\d{2}).*?gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/i', $line, $matches)) {
                $data['gaji_bersih'] = (float) str_replace(',', '', $matches[2]);
                continue;
            }
            
            // Handle standalone percentage like: "% Peratus Gaji Bersih : 45.22"
            if (preg_match('/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/i', $line, $matches)) {
                $data['peratus_gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
                continue;
            }
            
            // Extract individual fields if side-by-side patterns don't match
            
            // Extract Jumlah Pendapatan - handle spacing variations
            if (preg_match('/^jumlah\s+pendapatan\s*[:]*\s*([\d,]+\.\d{2})/i', $line, $matches)) {
                $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                continue;
            }
            
            // Look for standalone value that could be Jumlah Pendapatan if we found the label earlier
            if (preg_match('/^([\d,]+\.\d{2})\s*$/', $line, $matches) && $data['jumlah_pendapatan'] === null) {
                // Check previous lines for "Jumlah Pendapatan" 
                for ($k = max(0, $i-5); $k < $i; $k++) {
                    $prevLine = trim($lines[$k]);
                    if (preg_match('/^jumlah\s+pendapatan\s*$/i', $prevLine)) {
                        $value = (float) str_replace(',', '', $matches[1]);
                        // Validate it's a reasonable total income amount
                        if ($value > 1000 && $value < 50000) {
                            $data['jumlah_pendapatan'] = $value;
                            break;
                        }
                    }
                }
            }
            
            // Extract Jumlah Potongan - be more specific
            if (preg_match('/^jumlah\s+potongan\s*[:]*\s*([\d,]+\.\d{2})/i', $line, $matches)) {
                $data['jumlah_potongan'] = (float) str_replace(',', '', $matches[1]);
                continue;
            }
            
            // Extract Gaji Bersih - be more specific and avoid confusion with percentage
            if (preg_match('/^gaji\s+bersih\s*[:]*\s*([\d,]+\.\d{2})(?!\s*%)/i', $line, $matches)) {
                $data['gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
                continue;
            }
            
            // Extract % Peratus Gaji Bersih - be more specific
            if (preg_match('/^%?\s*peratus\s+gaji\s+bersih\s*[:]*\s*([\d,]+\.\d{2})/i', $line, $matches)) {
                $data['peratus_gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
                continue;
            }
        }
        
        return $data;
    }

    /**
     * Extract Gaji Pokok from the earnings section
     */
    private function extractGajiPokok(string $text): ?float
    {
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Look for "0001 Gaji Pokok" pattern with amount
            if (preg_match('/0001\s+gaji\s+pokok\s+([\d,]+\.\d{2})/i', $line, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
            
            // Alternative pattern without code
            if (preg_match('/gaji\s+pokok\s+([\d,]+\.\d{2})/i', $line, $matches)) {
                return (float) str_replace(',', '', $matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Enhanced payslip data extraction
     */
    private function extractPayslipDataAdvanced(string $text): array
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
            'debug_patterns' => [],
            'confidence_scores' => []
        ];

        // Normalize text
        $cleanText = $this->normalizeText($text);

        // First try the summary section extraction (more reliable for Malaysian payslips)
        $summaryData = $this->extractSummarySection($text);
        foreach ($summaryData as $field => $value) {
            if ($value !== null) {
                $data[$field] = $value;
                $data['debug_patterns'][] = "{$field}: extracted from summary section";
                $data['confidence_scores'][$field] = 95; // High confidence for summary section
            }
        }

        // Extract Gaji Pokok from earnings section
        $gajiPokok = $this->extractGajiPokok($text);
        if ($gajiPokok !== null) {
            $data['gaji_pokok'] = $gajiPokok;
            $data['debug_patterns'][] = "gaji_pokok: extracted from earnings section";
            $data['confidence_scores']['gaji_pokok'] = 90;
        }

        // Then extract each field using patterns for missing data
        foreach ($this->extractionPatterns as $field => $patterns) {
            if ($data[$field] !== null) continue; // Skip if already extracted from summary
            
            foreach ($patterns as $pattern) {
                try {
                    if (preg_match($pattern['regex'], $cleanText, $matches)) {
                        $value = $this->processFieldValue($field, $matches[1]);
                        if ($this->validateFieldValue($field, $value)) {
                            $data[$field] = $value;
                            $data['debug_patterns'][] = "{$field}: {$pattern['description']}";
                            $data['confidence_scores'][$field] = $pattern['confidence_weight'] * 100;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Pattern matching failed for field {$field}", [
                        'pattern' => $pattern['regex'],
                        'error' => $e->getMessage()
                    ]);
                    $data['debug_patterns'][] = "{$field}: pattern failed - {$e->getMessage()}";
                }
            }
        }

        // Calculate missing values if possible
        if ($data['peratus_gaji_bersih'] === null && $data['gaji_bersih'] !== null && $data['gaji_pokok'] !== null) {
            if ($data['gaji_pokok'] > 0) {
                $calculated = round(($data['gaji_bersih'] / $data['gaji_pokok']) * 100, 2);
                if ($calculated > 0 && $calculated <= 100) {
                    $data['peratus_gaji_bersih'] = $calculated;
                    $data['debug_patterns'][] = "peratus_gaji_bersih: calculated from gaji_bersih/gaji_pokok";
                    $data['confidence_scores']['peratus_gaji_bersih'] = 70;
                }
            }
        }

        if ($data['gaji_bersih'] === null && $data['jumlah_pendapatan'] !== null && $data['jumlah_potongan'] !== null) {
            $calculated = round($data['jumlah_pendapatan'] - $data['jumlah_potongan'], 2);
            if ($calculated > 0) {
                $data['gaji_bersih'] = $calculated;
                $data['debug_patterns'][] = "gaji_bersih: calculated from pendapatan-potongan";
                $data['confidence_scores']['gaji_bersih'] = 70;
            }
        }

        return $data;
    }

    /**
     * Validate extracted data
     */
    private function validateExtractedData(array $data): array
    {
        $errors = [];
        $warnings = [];
        
        // Validate salary ranges
        if ($data['gaji_bersih'] !== null) {
            if ($data['gaji_bersih'] < $this->validationRules['salary_range']['min'] || 
                $data['gaji_bersih'] > $this->validationRules['salary_range']['max']) {
                $errors[] = "Gaji bersih outside valid range";
            }
        }
        
        // Validate percentage
        if ($data['peratus_gaji_bersih'] !== null) {
            if ($data['peratus_gaji_bersih'] < $this->validationRules['percentage_range']['min'] || 
                $data['peratus_gaji_bersih'] > $this->validationRules['percentage_range']['max']) {
                $errors[] = "Percentage outside valid range";
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'field_completeness' => $this->calculateDataCompleteness($data)
        ];
    }

    /**
     * Check koperasi eligibility
     */
    private function checkKoperasiEligibility(array $extractedData): array
    {
        $simpleResults = [];
        $detailedResults = [];
        
        if ($extractedData['peratus_gaji_bersih'] === null) {
            return ['simple' => [], 'detailed' => []];
        }
        
        $koperasis = Koperasi::where('is_active', true)->get();
        
        foreach ($koperasis as $koperasi) {
            $eligibilityCheck = $this->performEligibilityCheck(
                $extractedData['peratus_gaji_bersih'],
                $koperasi->rules,
                $extractedData
            );
            
            $simpleResults[$koperasi->name] = $eligibilityCheck['eligible'];
            $detailedResults[$koperasi->name] = [
                'eligible' => $eligibilityCheck['eligible'],
                'reasons' => $eligibilityCheck['reasons'],
                'score' => $eligibilityCheck['score'] ?? 0,
            ];
        }
        
        return [
            'simple' => $simpleResults,
            'detailed' => $detailedResults
        ];
    }

    /**
     * Perform eligibility check
     */
    private function performEligibilityCheck(float $percentage, array $rules, array $extractedData): array
    {
        $eligible = true;
        $reasons = [];
        $score = 0;

        // Check percentage requirement
        if (isset($rules['max_peratus_gaji_bersih'])) {
            if ($percentage <= $rules['max_peratus_gaji_bersih']) {
                $score += 50;
                $reasons[] = "✅ Percentage requirement met ({$percentage}% ≤ {$rules['max_peratus_gaji_bersih']}%)";
            } else {
                $eligible = false;
                $reasons[] = "❌ Percentage too high ({$percentage}% > {$rules['max_peratus_gaji_bersih']}%)";
            }
        }

        // Check minimum basic salary
        if (isset($rules['min_gaji_pokok']) && $extractedData['gaji_pokok'] !== null) {
            if ($extractedData['gaji_pokok'] >= $rules['min_gaji_pokok']) {
                $score += 30;
                $reasons[] = "✅ Basic salary requirement met";
            } else {
                $eligible = false;
                $reasons[] = "❌ Basic salary too low";
            }
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'score' => $score
        ];
    }

    /**
     * Initialize extraction patterns based on real Malaysian payslip format
     */
    private function initializePatterns(): void
    {
        $this->extractionPatterns = [
            'nama' => [
                [
                    'regex' => '/nama\s*\n\s*:\s*([^\r\n]+)/im',
                    'description' => 'Name in multiline format (field and value on separate lines)',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/nama\s*:\s*([^\r\n]+?)(?:\s*no\.\s*gaji|$)/im',
                    'description' => 'Name field from payslip header',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/^\s*nama\s*:\s*(.+?)$/im',
                    'description' => 'Name on dedicated line',
                    'confidence_weight' => 0.8
                ]
            ],
            'no_gaji' => [
                [
                    'regex' => '/no\.\s*gaji\s*\n\s*:\s*([a-z0-9]+)/im',
                    'description' => 'Employee number in multiline format',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/no\.\s*gaji\s*:\s*([a-z0-9]+)/im',
                    'description' => 'Employee number from header',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/^\s*no\.\s*gaji\s*:\s*([a-z0-9]+)/im',
                    'description' => 'Employee number on dedicated line',
                    'confidence_weight' => 0.8
                ]
            ],
            'bulan' => [
                [
                    'regex' => '/bulan\s+(\d{2}\/\d{4})/im',
                    'description' => 'Month in MM/YYYY format',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/bulan\s*:\s*([^\r\n]+)/im',
                    'description' => 'Month field from header',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/^\s*bulan\s+(\d{2}\/\d{4})\s*$/im',
                    'description' => 'Month on standalone line',
                    'confidence_weight' => 0.9
                ]
            ],
            'gaji_pokok' => [
                [
                    'regex' => '/pendapatan.*?0001.*?amaun.*?([\d,]+\.\d{2}).*?gaji\s+pokok/ims',
                    'description' => 'Basic salary amount from earnings section with code 0001',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/0001.*?gaji\s+pokok.*?([\d,]+\.\d{2})/ims',
                    'description' => 'Basic salary with code 0001 (multiline)',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/0001\s+gaji\s+pokok.*?([\d,]+\.\d{2})/im',
                    'description' => 'Basic salary with code 0001',
                    'confidence_weight' => 0.85
                ],
                [
                    'regex' => '/gaji\s+pokok\s+([\d,]+\.\d{2})/im',
                    'description' => 'Basic salary direct amount',
                    'confidence_weight' => 0.8
                ]
            ],
            'jumlah_pendapatan' => [
                [
                    'regex' => '/jumlah\s+pendapatan\s*:\s*([\d,]+\.\d{2})/im',
                    'description' => 'Total earnings from summary',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/jumlah\s+pendapatan\s+([\d,]+\.\d{2})/im',
                    'description' => 'Total earnings without colon',
                    'confidence_weight' => 0.8
                ]
            ],
            'jumlah_potongan' => [
                [
                    'regex' => '/jumlah\s+potongan\s*:\s*([\d,]+\.\d{2})/im',
                    'description' => 'Total deductions from summary',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/jumlah\s+potongan\s+([\d,]+\.\d{2})/im',
                    'description' => 'Total deductions without colon',
                    'confidence_weight' => 0.8
                ]
            ],
            'gaji_bersih' => [
                [
                    'regex' => '/gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/im',
                    'description' => 'Net salary from summary',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/gaji\s+bersih\s+([\d,]+\.\d{2})/im',
                    'description' => 'Net salary without colon',
                    'confidence_weight' => 0.8
                ]
            ],
            'peratus_gaji_bersih' => [
                [
                    'regex' => '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/im',
                    'description' => 'Percentage with % symbol from summary',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/im',
                    'description' => 'Percentage without % symbol from summary',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/%\s*peratus\s+gaji\s+bersih\s+([\d,]+\.\d{2})/im',
                    'description' => 'Percentage with % symbol without colon',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/peratus\s+gaji\s+bersih\s+([\d,]+\.\d{2})/im',
                    'description' => 'Percentage without colon or symbol',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{1,2})\s*$/im',
                    'description' => 'Percentage at end of line with 1-2 decimal places',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/gaji\s+bersih\s*:\s*[\d,]+\.\d{2}.*?%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.\d{2})/im',
                    'description' => 'Percentage after gaji bersih in same context',
                    'confidence_weight' => 0.9
                ]
            ]
        ];
    }

    /**
     * Initialize validation rules
     */
    private function initializeValidationRules(): void
    {
        $this->validationRules = [
            'salary_range' => [
                'min' => $this->settingsService->get('general.min_salary_amount', 100),
                'max' => $this->settingsService->get('general.max_salary_amount', 50000)
            ],
            'percentage_range' => [
                'min' => $this->settingsService->get('general.min_percentage', 10),
                'max' => $this->settingsService->get('general.max_percentage', 100)
            ]
        ];
    }

    // Helper methods
    private function normalizeText(string $text): string
    {
        // Preserve line breaks for Malaysian payslip structure but clean up excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text); // Replace multiple spaces/tabs with single space
        $text = preg_replace('/\n\s*\n/', "\n", $text); // Remove empty lines
        $text = trim($text);
        return $text;
    }

    private function processFieldValue(string $field, string $rawValue): mixed
    {
        switch ($field) {
            case 'peratus_gaji_bersih':
            case 'gaji_bersih':
            case 'gaji_pokok':
                return (float) str_replace(',', '', trim($rawValue));
            default:
                return trim($rawValue);
        }
    }

    private function validateFieldValue(string $field, $value): bool
    {
        if ($value === null) return false;
        
        switch ($field) {
            case 'peratus_gaji_bersih':
                // More forgiving percentage validation
                return is_numeric($value) && $value >= 0 && $value <= 100;
            case 'gaji_bersih':
            case 'gaji_pokok':
            case 'jumlah_pendapatan':
            case 'jumlah_potongan':
                // More forgiving salary validation
                return is_numeric($value) && $value >= 0 && $value <= 100000;
            default:
                return !empty(trim($value));
        }
    }

    private function calculateConfidenceScore(array $data, string $text): float
    {
        if (empty($data['confidence_scores'])) {
            // Give a base confidence if we extracted any data at all
            $extractedFields = 0;
            foreach (['nama', 'peratus_gaji_bersih', 'gaji_bersih', 'gaji_pokok'] as $field) {
                if (!empty($data[$field])) $extractedFields++;
            }
            return $extractedFields > 0 ? 50 : 0;
        }
        
        $totalScore = array_sum($data['confidence_scores']);
        $averageScore = $totalScore / count($data['confidence_scores']);
        
        return round($averageScore, 2);
    }

    private function calculateDataCompleteness(array $data): float
    {
        $importantFields = ['gaji_bersih', 'peratus_gaji_bersih', 'gaji_pokok', 'nama'];
        $foundFields = 0;
        
        foreach ($importantFields as $field) {
            if ($data[$field] !== null) {
                $foundFields++;
            }
        }
        
        return round(($foundFields / count($importantFields)) * 100, 2);
    }

    private function determineFailureStage(\Exception $e): string
    {
        $message = $e->getMessage();
        
        if (strpos($message, 'extract') !== false) return 'text_extraction';
        if (strpos($message, 'OCR') !== false) return 'ocr_processing';
        if (strpos($message, 'pattern') !== false) return 'data_extraction';
        
        return 'unknown';
    }

    // Simplified OCR methods
    private function extractPdfText(string $filePath): string
    {
        $pdfToTextPath = $this->settingsService->get('ocr.pdftotext_path', '/usr/bin/pdftotext');
        return (new Pdf($pdfToTextPath))->setPdf($filePath)->text();
    }

    private function performOCRSpace(string $filePath): string
    {
        // Basic OCR.space implementation
        $settingsApiKey = $this->settingsService->get('ocr.ocrspace_api_key');
        $apiKey = !empty($settingsApiKey) ? $settingsApiKey : env('OCRSPACE_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('OCR.space API key not configured');
        }
        
        // Implementation would go here
        return "OCR text extracted";
    }

    private function performTesseractOCR(string $filePath): string
    {
        // Basic Tesseract implementation
        return "Tesseract OCR text";
    }
} 