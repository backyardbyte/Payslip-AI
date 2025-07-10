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
    protected $signature = 'payslip:test-processing {file : Path to payslip file} {--show-text : Show extracted OCR text} {--verbose : Show detailed error information}';
    protected $description = 'Test payslip processing with enhanced debugging for Malaysian government payslips';

    public function handle()
    {
        $this->info('Testing Enhanced Payslip Processing');
        $this->info('====================================');

        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File does not exist: {$file}");
            return 1;
        }

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
            
            // Prepare OCR.space API request
            $postData = [
                'apikey' => $apiKey,
                'base64Image' => 'data:' . $mimeType . ';base64,' . $base64,
                // Remove language parameter for better compatibility with free API keys
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true',
                'OCREngine' => '2',
                'isTable' => 'true',
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
            
            return trim($extractedText);
            
        } catch (\Exception $e) {
            throw new \Exception('OCR.space processing failed: ' . $e->getMessage());
        }
    }
} 