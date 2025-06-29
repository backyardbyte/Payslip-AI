<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Jobs\ProcessPayslip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class TestPayslipProcessing extends Command
{
    protected $signature = 'payslip:test-processing {--payslip-id= : Specific payslip ID to test}';
    protected $description = 'Test payslip processing on the latest uploaded file';

    public function handle()
    {
        $payslipId = $this->option('payslip-id');
        
        if ($payslipId) {
            $payslip = Payslip::find($payslipId);
        } else {
            $payslip = Payslip::latest()->first();
        }
        
        if (!$payslip) {
            $this->error('No payslips found');
            return 1;
        }

        $this->info("Testing payslip processing for ID: {$payslip->id}");
        $this->info("File: {$payslip->file_path}");
        
        $path = Storage::path($payslip->file_path);
        $mime = Storage::mimeType($payslip->file_path);
        
        $this->info("MIME type: {$mime}");
        $this->info("File exists: " . (file_exists($path) ? 'Yes' : 'No'));
        
        if ($mime === 'application/pdf') {
            $this->info("Testing OCR.space extraction on PDF...");
            try {
                // Use OCR.space for PDF processing
                $text = $this->performOCRSpace($path);
                $this->info("✅ OCR.space extraction successful");
                $this->info("Text length: " . strlen($text));
                
                // Show first few lines
                $lines = explode("\n", $text);
                $this->info("First 5 lines:");
                for ($i = 0; $i < min(5, count($lines)); $i++) {
                    $this->line("  " . trim($lines[$i]));
                }
                
            } catch (\Exception $e) {
                $this->error("❌ OCR.space extraction failed: " . $e->getMessage());
                $text = '';
            }
        } else {
            $this->info("Testing OCR.space extraction on image file...");
            try {
                $text = $this->performOCRSpace($path);
                $this->info("✅ OCR.space extraction successful");
                $this->info("Text length: " . strlen($text));
            } catch (\Exception $e) {
                $this->error("❌ OCR.space extraction failed: " . $e->getMessage());
                $text = '';
            }
        }
        
        if (!empty($text)) {
            $this->info("\nTesting data extraction patterns...");
            
            // Test basic patterns
            $patterns = [
                'nama' => '/nama\s*:\s*([^:]+?)(?:\s+no\.\s*gaji|$)/i',
                'no_gaji' => '/no\.?\s*gaji\s*:?\s*([^\s\n\r]+)/i',
                'bulan' => '/bulan\s*:?\s*(\d{2}\/\d{4})/i',
                'gaji_bersih' => '/gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
                'peratus' => '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $this->info("✅ {$field}: " . trim($matches[1]));
                } else {
                    $this->line("❌ {$field}: Not found");
                }
            }
        }
        
        $this->info("\nNow dispatching actual job...");
        
        // Reset payslip status
        $payslip->update([
            'status' => 'pending',
            'processing_started_at' => null,
            'processing_completed_at' => null,
            'processing_error' => null,
            'extracted_data' => null,
        ]);
        
        // Dispatch the job
        ProcessPayslip::dispatch($payslip);
        
        $this->info("✅ Job dispatched for payslip ID: {$payslip->id}");
        $this->info("Check the logs and database for results");
        
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
                'language' => 'eng',
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