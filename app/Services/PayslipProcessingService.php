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
            $ocrResult = $extractionResult['ocr_result'] ?? null;

            if (empty($text)) {
                throw new \Exception('No text could be extracted from the file');
            }

            // Process and extract payslip data
            $extractedData = $this->extractPayslipDataAdvanced($text, $ocrResult);
            
            // Log the extracted text for debugging if fields are missing
            if ($debugMode || $this->hasMissingCriticalFields($extractedData)) {
                Log::info('PayslipProcessingService OCR Text Debug', [
                    'payslip_id' => $payslip->id,
                    'text_length' => strlen($text),
                    'first_500_chars' => substr($text, 0, 500),
                    'extracted_fields' => array_filter($extractedData, function($value, $key) {
                        return !in_array($key, ['debug_patterns', 'confidence_scores']) && $value !== null;
                    }, ARRAY_FILTER_USE_BOTH)
                ]);
            }
            
            // Validate extracted data
            $validationResult = $this->validateExtractedData($extractedData);
            
            // Check koperasi eligibility
            $eligibilityResults = $this->checkKoperasiEligibility($extractedData);

            // Calculate processing metrics
            $processingTime = microtime(true) - $startTime;
            $confidence = $this->calculateConfidenceScore($extractedData, $text);

            // Prepare final data - flatten structure for frontend compatibility
            $finalData = array_merge($extractedData, [
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
                ],
                // Keep nested version for backwards compatibility
                'extracted_data' => $extractedData
            ]);

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
        $ocrResult = null;

        if ($mimeType === 'application/pdf') {
            try {
                $text = $this->extractPdfText($filePath);
                $method = 'pdftotext';
            } catch (\Exception $e) {
                $ocrResult = $this->performOCRSpace($filePath);
                $text = $this->getTextFromOcrResult($ocrResult);
                $method = 'ocr_space_fallback';
            }
        } else {
            $ocrMethod = $this->settingsService->get('ocr.method', 'ocrspace');
            
            if ($ocrMethod === 'ocrspace') {
                $ocrResult = $this->performOCRSpace($filePath);
                $text = $this->getTextFromOcrResult($ocrResult);
                $method = 'ocr_space';
            } else {
                $ocrResult = $this->performTesseractOCR($filePath);
                $text = $this->getTextFromOcrResult($ocrResult);
                $method = 'tesseract';
            }
        }

        return [
            'text' => $text,
            'method' => $method,
            'text_length' => strlen($text),
            'ocr_result' => $ocrResult,
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
        
        // Also create a version without line breaks for cross-line matching
        $textNoBreaks = preg_replace('/\s+/', ' ', $text);
        
        // Handle the most common Malaysian government payslip format where the summary appears like:
        // Jumlah Potongan
        // Gaji Bersih  
        // % Peratus Gaji Bersih
        // 
        // :
        // :
        // :
        //
        // 8,014.46
        // 1,252.03
        // 13.51
        
        // Find the index of the summary labels
        $jumlahPotonganIndex = -1;
        $gajiBersihIndex = -1;
        $peratusIndex = -1;
        $colonStartIndex = -1;
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            $lineLower = strtolower($line);
            
            // Find the summary section by looking for the pattern of labels
            if (preg_match('/^jumlah\s+potongan\s*$/i', $line)) {
                $jumlahPotonganIndex = $i;
                
                // Look for subsequent labels
                for ($j = $i + 1; $j < min($i + 5, count($lines)); $j++) {
                    $nextLine = trim($lines[$j]);
                    if (preg_match('/^gaji\s+bersih\s*$/i', $nextLine)) {
                        $gajiBersihIndex = $j;
                    } else if (preg_match('/^%?\s*peratus\s+gaji\s+bersih\s*$/i', $nextLine)) {
                        $peratusIndex = $j;
                    }
                }
                
                // Look for the colons section after the labels
                for ($j = max($jumlahPotonganIndex, $gajiBersihIndex, $peratusIndex) + 1; $j < count($lines) && $j < $i + 10; $j++) {
                    $colonLine = trim($lines[$j]);
                    // Check if this line contains only colons (possibly multiple)
                    if (preg_match('/^:+\s*$/', $colonLine)) {
                        $colonStartIndex = $j;
                        
                        // Count how many colons are on subsequent lines to understand the format
                        $colonCount = 1;
                        for ($k = $j + 1; $k < count($lines) && $k < $j + 5; $k++) {
                            if (preg_match('/^:+\s*$/', trim($lines[$k]))) {
                                $colonCount++;
                            } else {
                                break;
                            }
                        }
                        
                        // Now look for the values after the colons
                        $valueStartIndex = $colonStartIndex + $colonCount;
                        $values = [];
                        
                        // Collect numeric values after the colons
                        for ($k = $valueStartIndex; $k < count($lines) && $k < $valueStartIndex + 10; $k++) {
                            $valueLine = trim($lines[$k]);
                            if (preg_match('/^([\d,]+\.?\d*)\s*$/', $valueLine, $matches)) {
                                $values[] = (float) str_replace(',', '', $matches[1]);
                            } else if (!empty($valueLine) && !preg_match('/\(.*\)/', $valueLine) && !preg_match('/sila|bank|cukai/i', $valueLine)) {
                                // Stop if we hit non-numeric content that's not a note
                                break;
                            }
                        }
                        
                        // Map values based on the order of labels found
                        if ($jumlahPotonganIndex >= 0 && $gajiBersihIndex > $jumlahPotonganIndex && $peratusIndex > $gajiBersihIndex) {
                            // Standard order: Jumlah Potongan, Gaji Bersih, Peratus
                            if (count($values) >= 1) $data['jumlah_potongan'] = $values[0];
                            if (count($values) >= 2) $data['gaji_bersih'] = $values[1];
                            if (count($values) >= 3) $data['peratus_gaji_bersih'] = $values[2];
                        } else if (count($values) == 3) {
                            // Assume standard order if we have exactly 3 values
                            $data['jumlah_potongan'] = $values[0];
                            $data['gaji_bersih'] = $values[1];
                            $data['peratus_gaji_bersih'] = $values[2];
                        }
                        
                        break;
                    }
                }
                
                // If we found the summary section, stop looking
                if ($colonStartIndex >= 0) {
                    break;
                }
            }
        }
        
        // Alternative pattern: Look for "Jumlah Pendapatan" which often appears separately
        for ($i = 0; $i < count($lines) - 2; $i++) {
            $line = trim($lines[$i]);
            
            if (preg_match('/^jumlah\s+pendapatan\s*$/i', $line)) {
                // Check if next line is a colon
                if (isset($lines[$i+1]) && preg_match('/^\s*:\s*$/', trim($lines[$i+1]))) {
                    // Value should be on the line after the colon
                    if (isset($lines[$i+2])) {
                        $valueLine = trim($lines[$i+2]);
                        if (preg_match('/^([\d,]+\.?\d*)\s*$/', $valueLine, $matches)) {
                            $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                        }
                    }
                }
            }
        }
        
        // Fallback patterns for different formats
        if ($data['jumlah_pendapatan'] === null || $data['jumlah_potongan'] === null || 
            $data['gaji_bersih'] === null || $data['peratus_gaji_bersih'] === null) {
            
            // Try single-line patterns as fallback
            for ($i = 0; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                
                // Jumlah Pendapatan with colon and value on same line
                if ($data['jumlah_pendapatan'] === null && preg_match('/jumlah\s+pendapatan\s*:\s*([\d,]+\.?\d*)/i', $line, $matches)) {
                    $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
                }
                
                // Jumlah Potongan with colon and value on same line
                if ($data['jumlah_potongan'] === null && preg_match('/jumlah\s+potongan\s*:\s*([\d,]+\.?\d*)/i', $line, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    if ($value >= 0 && $value < 20000) {
                        $data['jumlah_potongan'] = $value;
                    }
                }
                
                // Gaji Bersih with colon and value on same line
                if ($data['gaji_bersih'] === null && preg_match('/gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i', $line, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    if ($value > 0 && $value < 50000) {
                        $data['gaji_bersih'] = $value;
                    }
                }
                
                // Peratus with colon and value on same line
                if ($data['peratus_gaji_bersih'] === null && preg_match('/%?\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i', $line, $matches)) {
                    $value = (float) str_replace(',', '', $matches[1]);
                    if ($value >= 0 && $value <= 100) {
                        $data['peratus_gaji_bersih'] = $value;
                    }
                }
            }
        }
        
        // Log extraction results for debugging
        if (array_filter($data)) {
            Log::info('Summary section extraction results', [
                'jumlah_pendapatan' => $data['jumlah_pendapatan'],
                'jumlah_potongan' => $data['jumlah_potongan'],
                'gaji_bersih' => $data['gaji_bersih'],
                'peratus_gaji_bersih' => $data['peratus_gaji_bersih'],
                'extraction_method' => $colonStartIndex >= 0 ? 'multi-line-colon-format' : 'single-line-fallback'
            ]);
        }
        
        return $data;
    }

    /**
     * Extract Gaji Pokok from the earnings section
     */
    private function extractGajiPokok(string $text): ?float
    {
        $lines = explode("\n", $text);
        
        // First priority: Look for the exact "0001 Gaji Pokok" pattern with amount in Pendapatan section
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Pattern 1: "0001 Gaji Pokok [amount]" - most reliable
            if (preg_match('/0001\s+gaji\s+pokok\s+([\d,]+\.?\d*)/i', $line, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value > 1000 && $value < 50000) {
                    return $value;
                }
            }
        }
        
        // Second priority: Look for structured format where "0001" and "Gaji Pokok" might be in table format
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Pattern 1: Check if this line contains "0001" and "Gaji Pokok" in tabular format
            if (preg_match('/0001.*gaji\s+pokok.*?([\d,]+\.\d{2})/i', $line, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value > 1000 && $value < 50000) {
                    return $value;
                }
            }
            
            // Pattern 2: Check if this line contains "0001" and look for "Gaji Pokok" in nearby lines
            if (preg_match('/^0001\s*$/i', $line) || preg_match('/^0001\s+/i', $line)) {
                // Look in next few lines for "Gaji Pokok" and amount
                for ($j = 0; $j <= 5 && ($i + $j) < count($lines); $j++) {
                    $nextLine = trim($lines[$i + $j]);
                    if (preg_match('/gaji\s+pokok/i', $nextLine)) {
                        // Found "Gaji Pokok", now look for amount in this line or nearby lines
                        if (preg_match('/([\d,]+\.\d{2})/i', $nextLine, $matches)) {
                            $value = (float) str_replace(',', '', $matches[1]);
                            if ($value > 1000 && $value < 50000) {
                                return $value;
                            }
                        }
                        // Also check lines after "Gaji Pokok"
                        for ($k = 1; $k <= 3 && ($i + $j + $k) < count($lines); $k++) {
                            $amountLine = trim($lines[$i + $j + $k]);
                            if (preg_match('/^([\d,]+\.\d{2})$/i', $amountLine, $matches)) {
                                $value = (float) str_replace(',', '', $matches[1]);
                                if ($value > 1000 && $value < 50000) {
                                    return $value;
                                }
                            }
                        }
                    }
                }
            }
            
            // Pattern 3: Handle the specific Malaysian format like "0001 Gaji Pokok	3,365.73" (with tabs)
            if (preg_match('/0001\s+gaji\s+pokok\s+([\d,]+\.\d{2})/i', $line, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value > 1000 && $value < 50000) {
                    return $value;
                }
            }
        }
        
        // Third priority: Look for any "Gaji Pokok" with reasonable amount
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/gaji\s+pokok.*?([\d,]+\.\d{2})/i', $line, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                if ($value > 1000 && $value < 50000) {
                    return $value;
                }
            }
        }
        
        // Fourth priority: Look in the earnings section specifically
        $inPendapatanSection = false;
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Detect if we're in the Pendapatan section
            if (preg_match('/^pendapatan\s*$/i', $line) || preg_match('/amaun.*rm/i', $line)) {
                $inPendapatanSection = true;
                continue;
            }
            
            // Stop when we reach Potongan section
            if (preg_match('/^potongan\s*$/i', $line) || preg_match('/jumlah\s+pendapatan/i', $line)) {
                $inPendapatanSection = false;
            }
            
            // Look for amounts in the Pendapatan section that could be Gaji Pokok
            if ($inPendapatanSection && preg_match('/([\d,]+\.\d{2})/i', $line, $matches)) {
                $value = (float) str_replace(',', '', $matches[1]);
                // First large amount in earnings section is usually Gaji Pokok
                if ($value > 2000 && $value < 20000) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    /**
     * Extract data from tabular sections (Pendapatan and Potongan)
     */
    private function extractTabularData(string $text): array
    {
        $data = [
            'gaji_pokok' => null,
            'jumlah_pendapatan' => null,
            'jumlah_potongan' => null,
            'individual_earnings' => [],
            'individual_deductions' => []
        ];
        
        $lines = explode("\n", $text);
        $currentSection = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Detect section headers
            if (preg_match('/^pendapatan\s*$/i', $line)) {
                $currentSection = 'earnings';
                continue;
            } elseif (preg_match('/^potongan\s*$/i', $line)) {
                $currentSection = 'deductions';
                continue;
            } elseif (preg_match('/jumlah\s+(pendapatan|potongan)/i', $line)) {
                $currentSection = 'summary';
                continue;
            }
            
            // Skip header lines
            if (preg_match('/amaun\s+\(rm\)/i', $line) || preg_match('/^\s*$/i', $line)) {
                continue;
            }
            
            // Extract earnings data
            if ($currentSection === 'earnings') {
                // Pattern: "0001 Gaji Pokok 3,365.73" or "0001 Gaji Pokok	3,365.73"
                if (preg_match('/(\d{4})\s+(.+?)\s+([\d,]+\.\d{2})/i', $line, $matches)) {
                    $code = $matches[1];
                    $description = trim($matches[2]);
                    $amount = (float) str_replace(',', '', $matches[3]);
                    
                    $data['individual_earnings'][] = [
                        'code' => $code,
                        'description' => $description,
                        'amount' => $amount
                    ];
                    
                    // Extract Gaji Pokok specifically
                    if (preg_match('/gaji\s+pokok/i', $description) && $amount > 1000 && $amount < 50000) {
                        $data['gaji_pokok'] = $amount;
                    }
                }
                
                // Alternative pattern: code and description on separate lines
                if (preg_match('/^(\d{4})\s*$/i', $line, $matches)) {
                    $code = $matches[1];
                    // Look for description and amount in next few lines
                    // This is handled by the main extraction logic
                }
            }
            
            // Extract deductions data
            if ($currentSection === 'deductions') {
                if (preg_match('/(\d{4})\s+(.+?)\s+([\d,]+\.\d{2})/i', $line, $matches)) {
                    $code = $matches[1];
                    $description = trim($matches[2]);
                    $amount = (float) str_replace(',', '', $matches[3]);
                    
                    $data['individual_deductions'][] = [
                        'code' => $code,
                        'description' => $description,
                        'amount' => $amount
                    ];
                }
            }
        }
        
        // Calculate totals from individual items if not found elsewhere
        if (!empty($data['individual_earnings']) && $data['jumlah_pendapatan'] === null) {
            $total = array_sum(array_column($data['individual_earnings'], 'amount'));
            if ($total > 0 && $total < 50000) {
                $data['jumlah_pendapatan'] = $total;
            }
        }
        
        if (!empty($data['individual_deductions']) && $data['jumlah_potongan'] === null) {
            $total = array_sum(array_column($data['individual_deductions'], 'amount'));
            if ($total >= 0 && $total < 20000) {
                $data['jumlah_potongan'] = $total;
            }
        }
        
        return $data;
    }

    /**
     * Enhanced payslip data extraction
     */
    private function extractPayslipDataAdvanced(string $text, ?array $ocrResult = null): array
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

        // First extract each field using patterns to get all available data
        foreach ($this->extractionPatterns as $field => $patterns) {
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

        // Then try the summary section extraction (HIGHEST PRIORITY - overwrites pattern matches)
        $summaryData = $this->extractSummarySection($text);
        foreach ($summaryData as $field => $value) {
            if ($value !== null) {
                $data[$field] = $value;
                $data['debug_patterns'][] = "{$field}: extracted from summary section (PRIORITY)";
                $data['confidence_scores'][$field] = 95; // High confidence for summary section
            }
        }

        // Extract data from tabular sections (HIGHEST PRIORITY for earnings/deductions)
        $tabularData = $this->extractTabularData($text);
        foreach ($tabularData as $field => $value) {
            if ($value !== null && in_array($field, ['gaji_pokok', 'jumlah_pendapatan', 'jumlah_potongan'])) {
                $data[$field] = $value;
                $data['debug_patterns'][] = "{$field}: extracted from tabular section (PRIORITY)";
                $data['confidence_scores'][$field] = 98; // Highest confidence for tabular extraction
            }
        }

        // Extract Gaji Pokok from earnings section (if not found in tabular or summary)
        if ($data['gaji_pokok'] === null) {
            $gajiPokok = $this->extractGajiPokok($text);
            if ($gajiPokok !== null) {
                $data['gaji_pokok'] = $gajiPokok;
                $data['debug_patterns'][] = "gaji_pokok: extracted from earnings section";
                $data['confidence_scores']['gaji_pokok'] = 90;
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

        // Fix: Try to calculate Gaji Bersih even if result might be negative (data extraction issue)
        if ($data['gaji_bersih'] === null && $data['jumlah_pendapatan'] !== null && $data['jumlah_potongan'] !== null) {
            $calculated = round($data['jumlah_pendapatan'] - $data['jumlah_potongan'], 2);
            // Remove the positive check - sometimes extraction gives wrong totals
            if (abs($calculated) > 0 && abs($calculated) < 50000) { // Allow negative but reasonable values
                $data['gaji_bersih'] = abs($calculated); // Use absolute value for now
                $data['debug_patterns'][] = "gaji_bersih: calculated from pendapatan-potongan (abs value used due to extraction issue)";
                $data['confidence_scores']['gaji_bersih'] = 50; // Lower confidence due to potential extraction error
            }
        }

        // Alternative: If we have percentage and gaji_pokok, calculate gaji_bersih (only if not directly extracted)
        if ($data['gaji_bersih'] === null && $data['peratus_gaji_bersih'] !== null && $data['gaji_pokok'] !== null) {
            if ($data['gaji_pokok'] > 0 && $data['peratus_gaji_bersih'] > 0) {
                $calculated = round(($data['peratus_gaji_bersih'] / 100) * $data['gaji_pokok'], 2);
                if ($calculated > 0 && $calculated < 50000) {
                    $data['gaji_bersih'] = $calculated;
                    $data['debug_patterns'][] = "gaji_bersih: calculated from percentage * gaji_pokok ({$data['peratus_gaji_bersih']}% * {$data['gaji_pokok']})";
                    $data['confidence_scores']['gaji_bersih'] = 85; // High confidence for this calculation
                }
            }
        }
        
        // If we still don't have percentage, try to calculate it from gaji_bersih and gaji_pokok
        if ($data['peratus_gaji_bersih'] === null && $data['gaji_bersih'] !== null && $data['gaji_pokok'] !== null) {
            if ($data['gaji_pokok'] > 0) {
                $calculated = round(($data['gaji_bersih'] / $data['gaji_pokok']) * 100, 2);
                if ($calculated > 0 && $calculated <= 100) {
                    $data['peratus_gaji_bersih'] = $calculated;
                    $data['debug_patterns'][] = "peratus_gaji_bersih: calculated from gaji_bersih/gaji_pokok ({$data['gaji_bersih']}/{$data['gaji_pokok']})";
                    $data['confidence_scores']['peratus_gaji_bersih'] = 75;
                }
            }
        }
        
        // Ensure percentage is saved as a number (not currency formatted)
        if ($data['peratus_gaji_bersih'] !== null) {
            $data['peratus_gaji_bersih'] = (float) $data['peratus_gaji_bersih'];
        }
        
        // Enhanced data validation and relationship checking
        $this->validateAndFixDataRelationships($data);
        
        // Validate extracted data relationships
        if ($data['jumlah_pendapatan'] !== null && $data['jumlah_potongan'] !== null) {
            // Check if potongan is greater than pendapatan (likely extraction error)
            if ($data['jumlah_potongan'] > $data['jumlah_pendapatan']) {
                // Try swapping them - common OCR misread
                $temp = $data['jumlah_pendapatan'];
                $data['jumlah_pendapatan'] = $data['jumlah_potongan'];
                $data['jumlah_potongan'] = $temp;
                $data['debug_patterns'][] = "WARNING: Swapped pendapatan/potongan due to invalid values";
            }
        }
        
        // Validate gaji bersih makes sense
        if ($data['gaji_bersih'] !== null && $data['gaji_pokok'] !== null) {
            // Gaji bersih should typically be less than gaji pokok but more than 20% of it
            $ratio = $data['gaji_bersih'] / $data['gaji_pokok'];
            if ($ratio < 0.2 || $ratio > 1.5) {
                $data['debug_patterns'][] = "WARNING: Gaji bersih/pokok ratio is suspicious: " . round($ratio * 100, 2) . "%";
                // Try recalculating if we have percentage
                if ($data['peratus_gaji_bersih'] !== null && $data['peratus_gaji_bersih'] > 20) {
                    $recalculated = round(($data['peratus_gaji_bersih'] / 100) * $data['gaji_pokok'], 2);
                    $data['gaji_bersih'] = $recalculated;
                    $data['debug_patterns'][] = "Recalculated gaji_bersih using percentage";
                }
            }
        }

        return $data;
    }

    /**
     * Validate and fix data relationships
     */
    private function validateAndFixDataRelationships(array &$data): void
    {
        // Fix 1: If Gaji Pokok is higher than expected vs Gaji Bersih
        if ($data['gaji_pokok'] !== null && $data['gaji_bersih'] !== null) {
            $ratio = $data['gaji_bersih'] / $data['gaji_pokok'];
            
            // Gaji Bersih should be less than Gaji Pokok (after deductions)
            if ($ratio > 1.2) { // Allow some margin for allowances
                $data['debug_patterns'][] = "WARNING: Gaji bersih (" . $data['gaji_bersih'] . ") higher than gaji pokok (" . $data['gaji_pokok'] . ") - possible extraction error";
                
                // If we have reliable percentage, recalculate gaji_bersih
                if ($data['peratus_gaji_bersih'] !== null && $data['peratus_gaji_bersih'] <= 100) {
                    $recalculated = round(($data['peratus_gaji_bersih'] / 100) * $data['gaji_pokok'], 2);
                    if ($recalculated > 0 && $recalculated < $data['gaji_pokok']) {
                        $data['gaji_bersih'] = $recalculated;
                        $data['debug_patterns'][] = "FIXED: Recalculated gaji_bersih using percentage";
                    }
                }
            }
            
            // If ratio is too low (less than 20%), it might be an error
            if ($ratio < 0.2 && $data['peratus_gaji_bersih'] !== null && $data['peratus_gaji_bersih'] > 20) {
                $recalculated = round(($data['peratus_gaji_bersih'] / 100) * $data['gaji_pokok'], 2);
                $data['gaji_bersih'] = $recalculated;
                $data['debug_patterns'][] = "FIXED: Gaji bersih too low, recalculated using percentage";
            }
        }
        
        // Fix 2: Validate Jumlah Pendapatan vs Gaji Pokok
        if ($data['jumlah_pendapatan'] !== null && $data['gaji_pokok'] !== null) {
            // Jumlah Pendapatan should be >= Gaji Pokok (includes allowances)
            if ($data['jumlah_pendapatan'] < $data['gaji_pokok']) {
                $data['debug_patterns'][] = "WARNING: Jumlah pendapatan (" . $data['jumlah_pendapatan'] . ") less than gaji pokok (" . $data['gaji_pokok'] . ")";
                
                // If the difference is significant, the values might be swapped
                if ($data['gaji_pokok'] / $data['jumlah_pendapatan'] > 2) {
                    $temp = $data['jumlah_pendapatan'];
                    $data['jumlah_pendapatan'] = $data['gaji_pokok'];
                    $data['gaji_pokok'] = $temp;
                    $data['debug_patterns'][] = "FIXED: Swapped jumlah_pendapatan and gaji_pokok";
                }
            }
        }
        
        // Fix 3: Validate calculation: Gaji Bersih = Jumlah Pendapatan - Jumlah Potongan
        if ($data['jumlah_pendapatan'] !== null && $data['jumlah_potongan'] !== null && $data['gaji_bersih'] !== null) {
            $calculated_gaji_bersih = $data['jumlah_pendapatan'] - $data['jumlah_potongan'];
            $difference = abs($calculated_gaji_bersih - $data['gaji_bersih']);
            $tolerance = $data['gaji_bersih'] * 0.1; // 10% tolerance
            
            if ($difference > $tolerance) {
                $data['debug_patterns'][] = "WARNING: Math doesn't add up - Pendapatan ({$data['jumlah_pendapatan']}) - Potongan ({$data['jumlah_potongan']}) ≠ Gaji Bersih ({$data['gaji_bersih']})";
                
                // If the calculated value is more reasonable, use it
                if ($calculated_gaji_bersih > 0 && $calculated_gaji_bersih < 50000) {
                    $data['gaji_bersih'] = round($calculated_gaji_bersih, 2);
                    $data['debug_patterns'][] = "FIXED: Used calculated gaji_bersih = pendapatan - potongan";
                }
            }
        }
        
        // Fix 4: Validate percentage calculation (be careful not to override correct extractions)
        if ($data['peratus_gaji_bersih'] !== null && $data['gaji_bersih'] !== null && $data['gaji_pokok'] !== null) {
            $calculated_percentage = round(($data['gaji_bersih'] / $data['gaji_pokok']) * 100, 2);
            $difference = abs($calculated_percentage - $data['peratus_gaji_bersih']);
            
            if ($difference > 10) { // Increased tolerance - only fix if major discrepancy
                $data['debug_patterns'][] = "WARNING: Major percentage mismatch - Calculated: {$calculated_percentage}%, Extracted: {$data['peratus_gaji_bersih']}%";
                
                // Only override if extracted percentage is clearly wrong (>100% or <0%)
                if ($data['peratus_gaji_bersih'] > 100 || $data['peratus_gaji_bersih'] < 0) {
                    if ($calculated_percentage > 0 && $calculated_percentage <= 100) {
                        $data['peratus_gaji_bersih'] = $calculated_percentage;
                        $data['debug_patterns'][] = "FIXED: Used calculated percentage (extracted was invalid)";
                    }
                } else {
                    // Keep the extracted value as it's likely more accurate from the summary section
                    $data['debug_patterns'][] = "KEPT: Using extracted percentage (likely more accurate from payslip)";
                }
            } else if ($difference > 5) {
                $data['debug_patterns'][] = "INFO: Minor percentage difference - Calculated: {$calculated_percentage}%, Extracted: {$data['peratus_gaji_bersih']}%";
            }
        }
        
        // Fix 5: If we have percentage but missing gaji_bersih or gaji_pokok, try to calculate
        if ($data['peratus_gaji_bersih'] !== null) {
            // Calculate gaji_bersih if missing
            if ($data['gaji_bersih'] === null && $data['gaji_pokok'] !== null) {
                $calculated = round(($data['peratus_gaji_bersih'] / 100) * $data['gaji_pokok'], 2);
                if ($calculated > 0 && $calculated < 50000) {
                    $data['gaji_bersih'] = $calculated;
                    $data['debug_patterns'][] = "CALCULATED: gaji_bersih from percentage × gaji_pokok";
                }
            }
            
            // Calculate gaji_pokok if missing
            if ($data['gaji_pokok'] === null && $data['gaji_bersih'] !== null && $data['peratus_gaji_bersih'] > 0) {
                $calculated = round(($data['gaji_bersih'] / $data['peratus_gaji_bersih']) * 100, 2);
                if ($calculated > 0 && $calculated < 50000) {
                    $data['gaji_pokok'] = $calculated;
                    $data['debug_patterns'][] = "CALCULATED: gaji_pokok from gaji_bersih ÷ percentage";
                }
            }
        }
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
        
        // Always return a structure, even if no percentage is available
        $koperasis = Koperasi::where('is_active', true)->get();
        
        if ($extractedData['peratus_gaji_bersih'] === null) {
            // Return empty eligibility for each koperasi
            foreach ($koperasis as $koperasi) {
                $simpleResults[$koperasi->name] = false;
                $detailedResults[$koperasi->name] = [
                    'eligible' => false,
                    'reasons' => ['Percentage not available for eligibility check'],
                    'score' => 0,
                ];
            }
            return ['simple' => $simpleResults, 'detailed' => $detailedResults];
        }
        
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
     * Perform eligibility check based only on peratus gaji bersih
     */
    private function performEligibilityCheck(float $percentage, array $rules, array $extractedData): array
    {
        $eligible = true;
        $reasons = [];
        $score = 100;

        // Check minimum take-home percentage requirement (only criteria)
        if (isset($rules['min_peratus_gaji_bersih'])) {
            if ($percentage >= $rules['min_peratus_gaji_bersih']) {
                $eligible = true;
                $reasons[] = "✅ ELIGIBLE: Take-home percentage ({$percentage}%) meets minimum requirement ({$rules['min_peratus_gaji_bersih']}%)";
                
                // Bonus scoring for higher percentages
                if ($percentage >= 85) {
                    $score = 100;
                    $reasons[] = "🌟 Excellent financial standing ({$percentage}% take-home)";
                } elseif ($percentage >= 75) {
                    $score = 90;
                    $reasons[] = "⭐ Very good financial standing ({$percentage}% take-home)";
                } elseif ($percentage >= 65) {
                    $score = 80;
                    $reasons[] = "👍 Good financial standing ({$percentage}% take-home)";
                } else {
                    $score = 70;
                    $reasons[] = "✓ Acceptable financial standing ({$percentage}% take-home)";
                }
            } else {
                $eligible = false;
                $score = 0;
                $reasons[] = "❌ NOT ELIGIBLE: Take-home percentage ({$percentage}%) is below minimum requirement ({$rules['min_peratus_gaji_bersih']}%)";
                $reasons[] = "💡 You need at least {$rules['min_peratus_gaji_bersih']}% take-home to qualify for this koperasi";
            }
        } else {
            $eligible = false;
            $score = 0;
            $reasons[] = "❌ No eligibility criteria defined for this koperasi";
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'score' => $score,
            'percentage_used' => $percentage,
            'minimum_required' => $rules['min_peratus_gaji_bersih'] ?? null
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
                    'regex' => '/nama\s*:\s*([A-Z][A-Z\s]+(?:BIN|BINTI|A\/L|A\/P)[A-Z\s]+?)(?:\s+NO)?\s*$/im',
                    'description' => 'Name with Malaysian name patterns (excluding NO suffix)',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/nama\s*:\s*([^\r\n]+?)(?=\s*(?:no\.?\s*gaji|employee|$))/im',
                    'description' => 'Name field from payslip header',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/nama\s*\n\s*:\s*([^\r\n]+)/im',
                    'description' => 'Name in multiline format',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/^\s*nama\s*:\s*(.+?)$/im',
                    'description' => 'Name on dedicated line',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/(?:nama|name)\s*[:\-]?\s*([A-Z][A-Za-z\s]+)/i',
                    'description' => 'Flexible name pattern',
                    'confidence_weight' => 0.7
                ]
            ],
            'no_gaji' => [
                [
                    'regex' => '/no\.?\s*gaji\s*:\s*(\d{6,})/im',
                    'description' => 'Employee number with at least 6 digits',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/no\.?\s*gaji\s*:\s*([A-Z0-9]{6,})/im',
                    'description' => 'Employee number alphanumeric',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/(?:no\.?\s*gaji|employee\s*(?:id|no))\s*[:\-]?\s*(\d+)/im',
                    'description' => 'Flexible employee number pattern',
                    'confidence_weight' => 0.85
                ],
                [
                    'regex' => '/no\.?\s*gaji\s*\n\s*:\s*([a-z0-9]+)/im',
                    'description' => 'Employee number in multiline format',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/^\s*no\.?\s*gaji\s*:\s*([a-z0-9]+)/im',
                    'description' => 'Employee number on dedicated line',
                    'confidence_weight' => 0.75
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
                    'regex' => '/0001\s+gaji\s+pokok\s+([\d,]+\.?\d*)/im',
                    'description' => 'Basic salary with code 0001',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/gaji\s+pokok\s*[:=]?\s*(?:RM\s*)?([\d,]+\.?\d*)/im',
                    'description' => 'Basic salary with optional RM',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/pendapatan.*?0001.*?(?:amaun|amount).*?([\d,]+\.?\d*).*?gaji\s+pokok/ims',
                    'description' => 'Basic salary from earnings section with code',
                    'confidence_weight' => 0.85
                ],
                [
                    'regex' => '/0001.*?gaji\s+pokok.*?([\d,]+\.?\d*)/ims',
                    'description' => 'Basic salary with code 0001 (multiline)',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/gaji\s+pokok[^\d]+([\d,]+\.?\d*)/i',
                    'description' => 'Basic salary flexible pattern',
                    'confidence_weight' => 0.75
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
                    'regex' => '/jumlah\s+potongan\s*[:=]\s*(?:RM\s*)?([\d,]+\.?\d*)/im',
                    'description' => 'Total deductions with colon/equals',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/jumlah\s+potongan\s+(?:RM\s*)?([\d,]+\.?\d*)/im',
                    'description' => 'Total deductions without colon',
                    'confidence_weight' => 0.85
                ],
                [
                    'regex' => '/(?:total\s+(?:deductions?|potongan))[^\d]+([\d,]+\.?\d*)/i',
                    'description' => 'Total deductions flexible',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/potongan[^\d]+([\d,]+\.?\d*)/i',
                    'description' => 'Deductions simple pattern',
                    'confidence_weight' => 0.7
                ]
            ],
            'gaji_bersih' => [
                [
                    'regex' => '/gaji\s+bersih\s*[:=]\s*(?:RM\s*)?([\d,]+\.?\d*)/im',
                    'description' => 'Net salary with colon/equals',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/gaji\s+bersih\s+(?:RM\s*)?([\d,]+\.?\d*)/im',
                    'description' => 'Net salary without colon',
                    'confidence_weight' => 0.85
                ],
                [
                    'regex' => '/(?:net\s+salary|gaji\s+bersih)[^\d]+([\d,]+\.?\d*)/i',
                    'description' => 'Net salary flexible pattern',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/pendapatan\s+bercukai\s*:\s*[\d,]+\.?\d*\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
                    'description' => 'Net salary after pendapatan bercukai',
                    'confidence_weight' => 0.9
                ]
            ],
            'peratus_gaji_bersih' => [
                [
                    'regex' => '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/im',
                    'description' => 'Percentage with % symbol from summary',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/im',
                    'description' => 'Percentage without % symbol from summary',
                    'confidence_weight' => 0.9
                ],
                [
                    'regex' => '/gaji\s+bersih\s*:\s*[\d,]+\.\d{2}\s+%?\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/im',
                    'description' => 'Percentage after gaji bersih in same line',
                    'confidence_weight' => 0.95
                ],
                [
                    'regex' => '/%\s*peratus\s+gaji\s+bersih\s+([\d,]+\.?\d*)/im',
                    'description' => 'Percentage with % symbol without colon',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/peratus\s+gaji\s+bersih\s+([\d,]+\.?\d*)/im',
                    'description' => 'Percentage without colon or symbol',
                    'confidence_weight' => 0.8
                ],
                [
                    'regex' => '/peratus\s+gaji\s*\n?\s*bersih\s*:\s*([\d,]+\.?\d*)/im',
                    'description' => 'Percentage with possible line break',
                    'confidence_weight' => 0.85
                ],
                [
                    'regex' => '/\b(\d{2}\.\d{1,2})%?\s*$/m',
                    'description' => 'Standalone percentage at end of line (fallback)',
                    'confidence_weight' => 0.6
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
        // Clean the raw value first
        $cleanValue = trim($rawValue);
        $cleanValue = preg_replace('/^RM\s*/i', '', $cleanValue); // Remove RM prefix
        $cleanValue = str_replace(',', '', $cleanValue); // Remove commas
        
        switch ($field) {
            case 'peratus_gaji_bersih':
            case 'gaji_bersih':
            case 'gaji_pokok':
            case 'jumlah_pendapatan':
            case 'jumlah_potongan':
                // Extract numeric value from string
                if (preg_match('/([\d.]+)/', $cleanValue, $matches)) {
                    return (float) $matches[1];
                }
                return null;
            case 'nama':
                // Clean name - remove extra spaces and normalize
                return preg_replace('/\s+/', ' ', strtoupper($cleanValue));
            case 'no_gaji':
                // Clean employee ID - remove non-alphanumeric
                return preg_replace('/[^A-Z0-9]/i', '', $cleanValue);
            default:
                return $cleanValue;
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
    
    private function hasMissingCriticalFields(array $data): bool
    {
        $criticalFields = ['nama', 'no_gaji', 'gaji_pokok', 'gaji_bersih', 'peratus_gaji_bersih'];
        foreach ($criticalFields as $field) {
            if (!isset($data[$field]) || $data[$field] === null) {
                return true;
            }
        }
        return false;
    }

    // Simplified OCR methods
    private function extractPdfText(string $filePath): string
    {
        $pdfToTextPath = $this->settingsService->get('ocr.pdftotext_path', '/usr/bin/pdftotext');
        return (new Pdf($pdfToTextPath))->setPdf($filePath)->text();
    }

    private function performOCRSpace(string $filePath): string
    {
        // Fix API key retrieval - check if settings value is empty and fallback to env
        $settingsApiKey = $this->settingsService->get('ocr.ocrspace_api_key');
        $apiKey = !empty($settingsApiKey) ? $settingsApiKey : env('OCRSPACE_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('OCR.space API key not configured. Please configure it in settings or .env file');
        }
        
        $debugMode = $this->settingsService->get('advanced.enable_debug_mode', false);
        
        try {
            // Read file and encode to base64
            $fileData = file_get_contents($filePath);
            $base64 = base64_encode($fileData);
            
            // Determine file type
            $mimeType = mime_content_type($filePath);
            
            // Prepare OCR.space API request with enhanced settings
            $postData = [
                'apikey' => $apiKey,
                'base64Image' => 'data:' . $mimeType . ';base64,' . $base64,
                // Remove language parameter for better compatibility with free API keys
                'isOverlayRequired' => 'true', // Changed to true to get word coordinates
                'detectOrientation' => 'true',
                'scale' => 'true',
                'OCREngine' => '1', // Engine 1 is more accurate for structured documents
                'isTable' => 'true', // Better for structured documents like payslips
                'filetype' => 'PDF', // Explicitly specify PDF
                'isCreateSearchablePdf' => 'false',
                'isSearchablePdfHideTextLayer' => 'false',
                'detectCheckbox' => 'false',
                'checkboxTemplate' => '0',
            ];
            
            // Make API request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ocrTimeout = $this->settingsService->get('ocr.api_timeout', 120); // Configurable timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $ocrTimeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Connection timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Payslip-AI/1.0'); // User agent
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
            
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
                Log::info('PayslipProcessingService OCR.space extraction completed', [
                    'text_length' => strlen($extractedText),
                    'file_parse_status' => $result['ParsedResults'][0]['FileParseExitCode'] ?? 'unknown'
                ]);
            }
            
            // If extraction is too short, try OCR Engine 2 as fallback
            if (strlen($extractedText) < 500 && $postData['OCREngine'] !== '2') {
                Log::info('OCR text too short, trying Engine 2 as fallback', [
                    'first_attempt_length' => strlen($extractedText)
                ]);
                
                // Try again with Engine 2
                $postData['OCREngine'] = '2';
                // Remove language parameter for Engine 2 as well
                
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, 'https://api.ocr.space/parse/image');
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, $ocrTimeout);
                curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch2, CURLOPT_USERAGENT, 'Payslip-AI/1.0');
                curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                
                $response2 = curl_exec($ch2);
                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                if ($httpCode2 === 200) {
                    $result2 = json_decode($response2, true);
                    if (isset($result2['ParsedResults'])) {
                        $extractedText2 = '';
                        foreach ($result2['ParsedResults'] as $parsedResult) {
                            if (isset($parsedResult['ParsedText'])) {
                                $extractedText2 .= $parsedResult['ParsedText'] . "\n";
                            }
                        }
                        
                        // Use Engine 2 result if it's longer
                        if (strlen($extractedText2) > strlen($extractedText)) {
                            Log::info('Using Engine 2 result (longer text)', [
                                'engine1_length' => strlen($extractedText),
                                'engine2_length' => strlen($extractedText2)
                            ]);
                            $extractedText = $extractedText2;
                        }
                    }
                }
            }
            
            return trim($extractedText);
            
        } catch (\Exception $e) {
            Log::error('PayslipProcessingService OCR.space processing failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            
            throw new \Exception('OCR.space processing failed: ' . $e->getMessage() . '. Please check your OCR.space API key configuration.');
        }
    }

    private function performTesseractOCR(string $filePath): string
    {
        // This method is a placeholder. Tesseract logic is complex and should
        // be handled carefully, potentially with image preprocessing.
        // For now, it will fall back to OCR.space if needed via the caller.
        Log::warning('Tesseract OCR method called but not fully implemented in PayslipProcessingService. Falling back.', [
            'file_path' => $filePath
        ]);

        // To avoid breaking flows that might select Tesseract, we can either
        // throw an exception or, more gracefully, try OCR.space as a backup.
        return $this->performOCRSpace($filePath);
    }

    /**
     * Helper to extract plain text from an OCR.space result array
     */
    private function getTextFromOcrResult(?array $ocrResult): string
    {
        if (!$ocrResult || !isset($ocrResult['ParsedResults'])) {
            return '';
        }

        $extractedText = '';
        foreach ($ocrResult['ParsedResults'] as $parsedResult) {
            if (isset($parsedResult['ParsedText'])) {
                $extractedText .= $parsedResult['ParsedText'] . "\n";
            }
        }

        return trim($extractedText);
    }

    /**
     * Process a payslip either synchronously or asynchronously based on settings
     */
    public function processPayslipWithMode(Payslip $payslip): array
    {
        $processingMode = $this->settingsService->get('advanced.processing_mode', 'async');
        
        if ($processingMode === 'sync') {
            return $this->processPayslipSync($payslip);
        } else {
            return $this->processPayslipAsync($payslip);
        }
    }

    /**
     * Process payslip synchronously (immediate processing)
     */
    private function processPayslipSync(Payslip $payslip): array
    {
        try {
            // Process immediately
            $result = $this->processPayslip($payslip);
            
            // Send notifications immediately
            $this->sendNotifications($payslip, $result);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Synchronous payslip processing failed', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Process payslip asynchronously (queue job)
     */
    private function processPayslipAsync(Payslip $payslip): array
    {
        // Dispatch the job to the queue
        \App\Jobs\ProcessPayslip::dispatch($payslip);
        
        return [
            'status' => 'queued',
            'payslip_id' => $payslip->id,
            'message' => 'Payslip queued for processing'
        ];
    }

    /**
     * Send notifications for processed payslip
     */
    private function sendNotifications(Payslip $payslip, array $result): void
    {
        try {
            // Send Telegram notification if applicable
            if ($payslip->source === 'telegram' && $payslip->telegram_chat_id) {
                $this->sendTelegramNotification($payslip, $result);
            }
            
            // Send WhatsApp notification if applicable
            if ($payslip->source === 'whatsapp' && $payslip->whatsapp_phone) {
                $this->sendWhatsAppNotification($payslip, $result);
            }
            
            Log::info('Notifications sent for payslip', [
                'payslip_id' => $payslip->id,
                'telegram_sent' => $payslip->source === 'telegram',
                'whatsapp_sent' => $payslip->source === 'whatsapp',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notifications for payslip', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Telegram notification
     */
    private function sendTelegramNotification(Payslip $payslip, array $result): void
    {
        if (!$payslip->telegram_chat_id) {
            return;
        }

        try {
            $telegramService = app(\App\Services\SimpleTelegramBotService::class);
            $detailedResults = $result['detailed_koperasi_results'] ?? [];
            $telegramService->sendProcessingResult($payslip, $detailedResults);
            
            Log::info("Sent Telegram notification for payslip {$payslip->id} to chat {$payslip->telegram_chat_id}");
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram notification for payslip {$payslip->id}: " . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWhatsAppNotification(Payslip $payslip, array $result): void
    {
        if (!$payslip->whatsapp_phone) {
            return;
        }

        // Check if WhatsApp bot is configured
        $accessToken = config('services.whatsapp.access_token');
        if (!$accessToken) {
            Log::warning("Skipping WhatsApp notification for payslip {$payslip->id}: Access token not configured");
            return;
        }

        try {
            $whatsappService = app(\App\Services\WhatsAppService::class);
            $detailedResults = $result['detailed_koperasi_results'] ?? [];
            $whatsappService->sendProcessingResult($payslip, $detailedResults);
            
            Log::info("Sent WhatsApp notification for payslip {$payslip->id} to {$payslip->whatsapp_phone}");
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp notification for payslip {$payslip->id}: " . $e->getMessage());
        }
    }
} 