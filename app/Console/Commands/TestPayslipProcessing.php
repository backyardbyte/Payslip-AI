<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Jobs\ProcessPayslip;
use App\Services\PayslipProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class TestPayslipProcessing extends Command
{
    protected $signature = 'payslip:test-processing {file? : Path to payslip file} {--payslip-id= : Test with existing payslip ID} {--show-text : Show extracted OCR text} {--verbose : Show detailed error information}';
    protected $description = 'Test payslip processing with enhanced debugging for Malaysian government payslips';

    public function handle()
    {
        $this->info('Testing Enhanced Payslip Processing');
        $this->info('====================================');

        // Handle existing payslip testing for production
        if ($this->option('payslip-id')) {
            return $this->testExistingPayslip($this->option('payslip-id'));
        }

        $file = $this->argument('file');
        if (!$file) {
            $this->error('Please provide either a file path or --payslip-id option');
            return 1;
        }
        
        if (!file_exists($file)) {
            $this->error("File does not exist: {$file}");
            return 1;
        }

        return $this->testFileDirectly($file);
    }

    private function testExistingPayslip($payslipId)
    {
        $payslip = \App\Models\Payslip::find($payslipId);
        if (!$payslip) {
            $this->error("Payslip with ID {$payslipId} not found");
            return 1;
        }

        $this->info("Testing existing payslip ID: {$payslipId}");
        $this->info("File: {$payslip->file_path}");
        $this->info("Current status: {$payslip->status}");

        try {
            // Get the service and process
            $payslipService = app(\App\Services\PayslipProcessingService::class);
            
            // Reset payslip status
            $payslip->update([
                'status' => 'pending',
                'processing_started_at' => null,
                'processing_completed_at' => null,
                'processing_error' => null,
                'extracted_data' => null,
            ]);

            $this->info("Processing payslip with enhanced extraction...");
            $result = $payslipService->processPayslip($payslip);
            
            $this->info("✅ Processing completed successfully!");
            
            // Display results
            $this->displayResults($result);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Processing failed: ' . $e->getMessage());
            if ($this->option('verbose')) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            return 1;
        }
    }

    private function testFileDirectly($file)
    {
        try {
            // Test OCR extraction
            $this->info('Step 1: Testing OCR Text Extraction...');
            $text = $this->performOCRSpace($file);
            $this->info("✓ OCR extraction completed. Text length: " . strlen($text) . " characters");
            
            if ($this->option('show-text')) {
                $this->info("\n--- EXTRACTED TEXT ---");
                $this->line($text);
                $this->info("--- END TEXT ---\n");
            }

            // Test enhanced data extraction
            $this->info('Step 2: Testing Enhanced Data Extraction...');
            $payslipService = app(\App\Services\PayslipProcessingService::class);
            
            // Use reflection to access private method for testing
            $reflection = new \ReflectionClass($payslipService);
            $extractMethod = $reflection->getMethod('extractPayslipDataAdvanced');
            $extractMethod->setAccessible(true);
            
            $extractedData = $extractMethod->invoke($payslipService, $text, null);
            
            $this->info('✓ Data extraction completed');
            
            // Display extracted data
            $this->displayExtractedData($extractedData);

            // Test summary section extraction specifically
            $this->info("\nStep 3: Testing Summary Section Extraction...");
            $summaryMethod = $reflection->getMethod('extractSummarySection');
            $summaryMethod->setAccessible(true);
            $summaryData = $summaryMethod->invoke($payslipService, $text);
            
            $this->info("Summary Section Results:");
            foreach ($summaryData as $field => $value) {
                $displayValue = $value !== null ? $value : 'NULL';
                $this->line("  - {$field}: {$displayValue}");
            }

            // Test Gaji Pokok extraction specifically
            $this->info("\nStep 4: Testing Gaji Pokok Extraction...");
            $gajipokokMethod = $reflection->getMethod('extractGajiPokok');
            $gajipokokMethod->setAccessible(true);
            $gajiPokok = $gajipokokMethod->invoke($payslipService, $text);
            
            $displayGajiPokok = $gajiPokok !== null ? $gajiPokok : 'NULL';
            $this->info("Gaji Pokok: {$displayGajiPokok}");

            // Test validation
            $this->info("\nStep 5: Testing Data Validation...");
            $validateMethod = $reflection->getMethod('validateExtractedData');
            $validateMethod->setAccessible(true);
            $validationResult = $validateMethod->invoke($payslipService, $extractedData);
            
            $this->info("Validation passed: " . ($validationResult['passed'] ? 'YES' : 'NO'));
            if (!empty($validationResult['warnings'])) {
                $this->warn("Warnings:");
                foreach ($validationResult['warnings'] as $warning) {
                    $this->line("  - {$warning}");
                }
            }

            // Calculate final confidence score
            $confidenceMethod = $reflection->getMethod('calculateConfidenceScore');
            $confidenceMethod->setAccessible(true);
            $confidence = $confidenceMethod->invoke($payslipService, $extractedData, $text);
            
            $this->info("\n--- FINAL RESULTS ---");
            $this->info("Overall Confidence Score: {$confidence}%");
            
            // Success indicator
            $criticalFields = ['nama', 'gaji_bersih', 'peratus_gaji_bersih', 'gaji_pokok'];
            $extractedCount = 0;
            foreach ($criticalFields as $field) {
                if (!empty($extractedData[$field])) {
                    $extractedCount++;
                }
            }
            
            $this->info("Critical Fields Extracted: {$extractedCount}/4");
            
            if ($extractedCount >= 3) {
                $this->info("✅ EXTRACTION SUCCESS - Most critical fields found!");
            } elseif ($extractedCount >= 2) {
                $this->warn("⚠️  PARTIAL SUCCESS - Some critical fields missing");
            } else {
                $this->error("❌ EXTRACTION FAILED - Too few critical fields found");
            }

        } catch (\Exception $e) {
            $this->error('Processing failed: ' . $e->getMessage());
            if ($this->option('verbose')) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            return 1;
        }

        return 0;
    }

    private function displayResults($result)
    {
        $this->info("\n--- PROCESSING RESULTS ---");
        
        if (isset($result['nama'])) {
            $this->info("Name: " . $result['nama']);
        }
        if (isset($result['no_gaji'])) {
            $this->info("Employee ID: " . $result['no_gaji']);
        }
        if (isset($result['bulan'])) {
            $this->info("Period: " . $result['bulan']);
        }
        if (isset($result['gaji_pokok'])) {
            $this->info("Basic Salary: RM " . number_format($result['gaji_pokok'], 2));
        }
        if (isset($result['jumlah_pendapatan'])) {
            $this->info("Total Income: RM " . number_format($result['jumlah_pendapatan'], 2));
        }
        if (isset($result['jumlah_potongan'])) {
            $this->info("Total Deductions: RM " . number_format($result['jumlah_potongan'], 2));
        }
        if (isset($result['gaji_bersih'])) {
            $this->info("Net Salary: RM " . number_format($result['gaji_bersih'], 2));
        }
        if (isset($result['peratus_gaji_bersih'])) {
            $this->info("Salary Percentage: " . $result['peratus_gaji_bersih'] . "%");
        }

        if (isset($result['processing_metadata']['confidence_score'])) {
            $this->info("Confidence Score: " . $result['processing_metadata']['confidence_score'] . "%");
        }

        if (isset($result['koperasi_results'])) {
            $eligible = array_filter($result['koperasi_results'], fn($r) => $r['eligible'] ?? false);
            $this->info("Koperasi Eligible: " . count($eligible) . " out of " . count($result['koperasi_results']));
        }
    }

    private function displayExtractedData($extractedData)
    {
        $this->info("\n--- EXTRACTED DATA ---");
        foreach ($extractedData as $field => $value) {
            if ($field === 'debug_patterns') {
                $this->info("Debug Patterns:");
                foreach ($value as $pattern) {
                    $this->line("  - {$pattern}");
                }
            } elseif ($field === 'confidence_scores') {
                $this->info("Confidence Scores:");
                foreach ($value as $scorefield => $score) {
                    $this->line("  - {$scorefield}: {$score}%");
                }
            } elseif (!is_array($value)) {
                $displayValue = $value !== null ? $value : 'NULL';
                $this->info("{$field}: {$displayValue}");
            }
        }
    }
    
    /**
     * Perform OCR using OCR.space API
     */
    private function performOCRSpace(string $filePath): string
    {
        // Get API key from environment
        $apiKey = env('OCRSPACE_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('OCR.space API key not configured. Please set OCRSPACE_API_KEY in your .env file');
        }
        
        try {
            // Read file and encode to base64
            $fileData = file_get_contents($filePath);
            $base64 = base64_encode($fileData);
            
            // Determine file type
            $mimeType = mime_content_type($filePath);
            
            // Use the same enhanced settings as production
            $postData = [
                'apikey' => $apiKey,
                'base64Image' => 'data:' . $mimeType . ';base64,' . $base64,
                // Remove language parameter for better compatibility with free API keys
                'isOverlayRequired' => 'false', // False for better text extraction
                'detectOrientation' => 'true',
                'scale' => 'true',
                'OCREngine' => '1', // Engine 1 is better for structured documents
                'isTable' => 'true', // Critical for Malaysian payslip tabular format
                'filetype' => 'PDF',
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Payslip-AI/1.0');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
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
                throw new \Exception('Invalid OCR.space API response: Failed to decode JSON');
            }
            
            if (!isset($result['ParsedResults'])) {
                throw new \Exception('Invalid OCR.space API response: Missing ParsedResults');
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
            
            // Try Engine 2 if initial result is poor
            if (strlen($extractedText) < 500 || !preg_match('/gaji|pendapatan|potongan/i', $extractedText)) {
                $this->info("OCR Engine 1 result poor, trying Engine 2...");
                
                $postData['OCREngine'] = '2';
                
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, 'https://api.ocr.space/parse/image');
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 120);
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
                        
                        // Use Engine 2 if it's better
                        if (strlen($extractedText2) > strlen($extractedText) * 1.2 || 
                            preg_match_all('/gaji|pendapatan|potongan|peratus/i', $extractedText2) > 
                            preg_match_all('/gaji|pendapatan|potongan|peratus/i', $extractedText)) {
                            $this->info("Using Engine 2 result (better quality)");
                            $extractedText = $extractedText2;
                        }
                    }
                }
            }
            
            return trim($extractedText);
            
        } catch (\Exception $e) {
            throw new \Exception('OCR.space processing failed: ' . $e->getMessage());
        }
    }
} 