<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ShowOcrText extends Command
{
    protected $signature = 'payslip:show-ocr-text {--payslip-id= : The ID of the payslip to extract text from} {--file= : Direct file path to extract text from} {--method=auto : OCR method (auto, google_vision, ocrspace)}';
    protected $description = 'Extract and display OCR text from a payslip';

    public function handle()
    {
        $payslipId = $this->option('payslip-id');
        $filePath = $this->option('file');
        $method = $this->option('method');
        
        if (!$payslipId && !$filePath) {
            $this->error('Please provide either --payslip-id or --file option');
            return 1;
        }

        try {
            if ($payslipId) {
                // Process existing payslip
                $payslip = \App\Models\Payslip::find($payslipId);
                if (!$payslip) {
                    $this->error("Payslip with ID {$payslipId} not found");
                    return 1;
                }
                
                $fullPath = Storage::path($payslip->file_path);
                $this->info("Processing payslip ID: {$payslipId}");
                $this->info("File: {$payslip->file_path}");
            } else {
                // Process direct file
                $fullPath = $filePath;
                if (!file_exists($fullPath)) {
                    $this->error("File not found: {$fullPath}");
                    return 1;
                }
                
                $this->info("Processing file: {$fullPath}");
            }
            
            // Determine OCR method
            if ($method === 'auto') {
                $method = env('OCR_METHOD', 'google_vision');
            }
            
            $this->info("OCR Method: {$method}");
            $this->info("Starting OCR extraction...\n");
            
            // Extract text based on method
            if ($method === 'google_vision') {
                $text = $this->performGoogleVisionOCR($fullPath);
            } elseif ($method === 'ocrspace') {
                $text = $this->performOCRSpaceOCR($fullPath);
            } else {
                $this->error("Unsupported OCR method: {$method}");
                return 1;
            }
            
            $this->info("OCR extraction completed!");
            $this->info("Text length: " . strlen($text) . " characters");
            $this->info("Line count: " . substr_count($text, "\n") + 1);
            
            $this->line("\n" . str_repeat("=", 60));
            $this->line("EXTRACTED TEXT:");
            $this->line(str_repeat("=", 60));
            $this->line($text);
            $this->line(str_repeat("=", 60));
            
            // Show some basic analysis
            $this->info("\nTEXT ANALYSIS:");
            $malayKeywords = preg_match_all('/\b(gaji|pendapatan|potongan|peratus|jumlah|bersih|pokok)\b/i', $text);
            $this->info("- Malay keywords found: {$malayKeywords}");
            
            $numbers = preg_match_all('/\d{1,3}(?:,\d{3})*\.?\d*/', $text);
            $this->info("- Numbers found: {$numbers}");
            
            $percentages = preg_match_all('/\d+\.?\d*\s*%/', $text);
            $this->info("- Percentages found: {$percentages}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('OCR extraction failed: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Extract text using OCR.space API
     */
    private function performOCRSpaceOCR(string $filePath): string
    {
        $apiKey = env('OCRSPACE_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('OCR.space API key not configured');
        }
        
        $fileData = file_get_contents($filePath);
        $base64 = base64_encode($fileData);
        $mimeType = mime_content_type($filePath);
        
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
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('OCR.space API request failed with HTTP ' . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['ParsedResults']) || empty($result['ParsedResults'])) {
            throw new \Exception('Invalid OCR.space response or no text found');
        }
        
        return $result['ParsedResults'][0]['ParsedText'] ?? '';
    }

    /**
     * Extract text using Google Vision API
     */
    private function performGoogleVisionOCR(string $filePath): string
    {
        $apiKey = env('GOOGLE_VISION_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('Google Vision API key not configured');
        }
        
        // Determine file type and prepare image data
        $mimeType = mime_content_type($filePath);
        $base64 = '';
        
        if ($mimeType === 'application/pdf') {
            // For PDFs, we need to convert to image first
            $base64 = $this->convertPdfToImageBase64($filePath);
        } else {
            // For images, read directly
            $fileData = file_get_contents($filePath);
            $base64 = base64_encode($fileData);
        }
        
        $postData = [
            'requests' => [
                [
                    'image' => [
                        'content' => $base64
                    ],
                    'features' => [
                        [
                            'type' => 'DOCUMENT_TEXT_DETECTION',
                            'maxResults' => 1
                        ]
                    ],
                    'imageContext' => [
                        'languageHints' => ['en', 'ms'] // English and Malay for Malaysian payslips
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://vision.googleapis.com/v1/images:annotate?key=' . $apiKey);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Google Vision API request failed with HTTP ' . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new \Exception('Invalid Google Vision API response');
        }
        
        // Check for API errors
        if (isset($result['error'])) {
            $errorMsg = $result['error']['message'] ?? 'Unknown Google Vision API error';
            throw new \Exception('Google Vision API error: ' . $errorMsg);
        }
        
        // Extract text from response
        if (isset($result['responses'][0]['fullTextAnnotation']['text'])) {
            return $result['responses'][0]['fullTextAnnotation']['text'];
        } elseif (isset($result['responses'][0]['textAnnotations'][0]['description'])) {
            return $result['responses'][0]['textAnnotations'][0]['description'];
        } else {
            // Check if there's an error in the response
            if (isset($result['responses'][0]['error'])) {
                $errorMsg = $result['responses'][0]['error']['message'] ?? 'Unknown error';
                throw new \Exception('Google Vision API processing error: ' . $errorMsg);
            }
            
            throw new \Exception('Google Vision API returned no text data');
        }
    }

    /**
     * Convert PDF to image base64 for Google Vision API
     */
    private function convertPdfToImageBase64(string $pdfPath): string
    {
        try {
            // Try using Imagick if available
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                $imagick->setResolution(300, 300); // High resolution for better OCR
                $imagick->readImage($pdfPath . '[0]'); // Read first page only
                $imagick->setImageFormat('png');
                $imagick->setImageCompressionQuality(100);
                
                // Get image blob and encode to base64
                $imageBlob = $imagick->getImageBlob();
                $imagick->clear();
                $imagick->destroy();
                
                return base64_encode($imageBlob);
            }
            
            // Fallback: Try using ghostscript via exec
            $tempImagePath = sys_get_temp_dir() . '/payslip_' . uniqid() . '.png';
            
            // Use gs (ghostscript) to convert PDF to image
            $gsCommand = sprintf(
                'gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s 2>/dev/null',
                escapeshellarg($tempImagePath),
                escapeshellarg($pdfPath)
            );
            
            exec($gsCommand, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tempImagePath)) {
                $imageData = file_get_contents($tempImagePath);
                unlink($tempImagePath); // Clean up temp file
                return base64_encode($imageData);
            }
            
            throw new \Exception('PDF to image conversion failed. Please install ImageMagick or GhostScript for PDF support with Google Vision API.');
            
        } catch (\Exception $e) {
            throw new \Exception('PDF conversion failed: ' . $e->getMessage() . '. For PDF support with Google Vision API, please install ImageMagick or GhostScript.');
        }
    }
} 