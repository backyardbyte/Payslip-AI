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
            
            // Use the same extraction logic as the main job
            $extractedData = $this->extractPayslipData($text);
            
            $fields = [
                'nama' => 'Nama',
                'no_gaji' => 'No. Gaji', 
                'bulan' => 'Bulan',
                'gaji_bersih' => 'Gaji Bersih',
                'peratus_gaji_bersih' => 'Peratus Gaji Bersih',
                'gaji_pokok' => 'Gaji Pokok',
                'jumlah_pendapatan' => 'Jumlah Pendapatan',
                'jumlah_potongan' => 'Jumlah Potongan'
            ];
            
            foreach ($fields as $key => $label) {
                $value = $extractedData[$key] ?? null;
                if ($value !== null) {
                    if (is_numeric($value)) {
                        $displayValue = is_float($value) ? number_format($value, 2) : $value;
                    } else {
                        $displayValue = $value;
                    }
                    $this->info("✅ {$label}: {$displayValue}");
                } else {
                    $this->line("❌ {$label}: Not found");
                }
            }
            
            // Show debug patterns
            if (!empty($extractedData['debug_patterns'])) {
                $this->info("\nDebug patterns matched:");
                foreach ($extractedData['debug_patterns'] as $pattern) {
                    $this->line("  • {$pattern}");
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
        $processingService = app(PayslipProcessingService::class);
        
        try {
            $result = $processingService->processPayslipWithMode($payslip);
            $this->info("✅ Processing completed for payslip ID: {$payslip->id}");
            $this->info("Status: " . ($result['status'] ?? 'completed'));
            $this->info("Check the logs and database for detailed results");
        } catch (\Exception $e) {
            $this->error("❌ Processing failed for payslip ID: {$payslip->id}");
            $this->error("Error: " . $e->getMessage());
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
    
    /**
     * Extract payslip data using the same logic as the main job
     */
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

        // Extract Nama - handle Malaysian government payslip format
        if (preg_match('/nama\s*:\s*([^:]+?)(?:\s+no\.\s*gaji|$)/i', $cleanText, $matches)) {
            $data['nama'] = trim($matches[1]);
            $data['debug_patterns'][] = 'nama found (inline refined)';
        } elseif (preg_match('/nama\s*:?\s*([^\n\r]+)/i', $cleanText, $matches)) {
            $candidateName = trim($matches[1]);
            if (!empty($candidateName) && strtolower($candidateName) !== 'nama') {
                $data['nama'] = $candidateName;
                $data['debug_patterns'][] = 'nama found (inline)';
            }
        } elseif (preg_match('/nama\s*\n\s*:\s*([^\n\r]+)/i', $originalText, $matches)) {
            $data['nama'] = trim($matches[1]);
            $data['debug_patterns'][] = 'nama found (multiline with colon)';
        } elseif (preg_match('/nama\s*\n\s*([^\n\r]+)/i', $originalText, $matches)) {
            $candidateName = trim($matches[1]);
            if (!empty($candidateName) && !preg_match('/no\.\s*gaji|kump\s*ptj/i', $candidateName)) {
                $data['nama'] = $candidateName;
                $data['debug_patterns'][] = 'nama found (multiline direct)';
            }
        }
        
        // Additional pattern for Malaysian government format
        if (empty($data['nama'])) {
            $lines = preg_split('/\r\n|\r|\n/', $originalText);
            foreach ($lines as $index => $line) {
                if (preg_match('/^\s*nama\s*$/i', trim($line))) {
                    for ($i = $index + 1; $i < min(count($lines), $index + 4); $i++) {
                        $nextLine = trim($lines[$i]);
                        if (!empty($nextLine) && 
                            !preg_match('/^\s*:\s*$/', $nextLine) && 
                            !preg_match('/no\.\s*gaji|kump\s*ptj|jabatan|kementerian/i', $nextLine)) {
                            $data['nama'] = $nextLine;
                            $data['debug_patterns'][] = 'nama found (standalone line search)';
                            break 2;
                        }
                    }
                }
            }
        }

        // Extract No. Gaji - handle Malaysian government format
        if (preg_match('/no\.?\s*gaji\s*:?\s*([^\s\n\r]+)/i', $cleanText, $matches)) {
            $candidateNumber = trim($matches[1]);
            if (!empty($candidateNumber) && strtolower($candidateNumber) !== 'gaji') {
                $data['no_gaji'] = $candidateNumber;
                $data['debug_patterns'][] = 'no_gaji found (inline)';
            }
        } elseif (preg_match('/no\.?\s*gaji\s*\n\s*:\s*([^\n\r]+)/i', $originalText, $matches)) {
            $data['no_gaji'] = trim($matches[1]);
            $data['debug_patterns'][] = 'no_gaji found (multiline)';
        }
        
        // Pattern for Malaysian government format: "No. Gaji	Kump PTJ/PTJ	: 53 / 53250101"
        if (empty($data['no_gaji'])) {
            if (preg_match('/no\.?\s*gaji.*?:\s*(\d+)\s*\/?\s*(\d+)/i', $cleanText, $matches)) {
                $longNumber = trim($matches[2]);
                $shortNumber = trim($matches[1]);
                $data['no_gaji'] = strlen($longNumber) > strlen($shortNumber) ? $longNumber : $shortNumber;
                $data['debug_patterns'][] = 'no_gaji found (Malaysian format with numbers)';
            } elseif (preg_match('/no\.?\s*gaji.*?:\s*([a-z0-9]+)/i', $cleanText, $matches)) {
                $data['no_gaji'] = trim($matches[1]);
                $data['debug_patterns'][] = 'no_gaji found (Malaysian format general)';
            }
        }
        
        // Look for standalone "No. Gaji" then find numbers in next lines
        if (empty($data['no_gaji'])) {
            $lines = preg_split('/\r\n|\r|\n/', $originalText);
            foreach ($lines as $index => $line) {
                if (preg_match('/no\.?\s*gaji/i', trim($line))) {
                    for ($i = $index; $i < min(count($lines), $index + 3); $i++) {
                        $checkLine = trim($lines[$i]);
                        if (preg_match('/(\d{8,}|\d{4,6})/', $checkLine, $matches)) {
                            $data['no_gaji'] = trim($matches[1]);
                            $data['debug_patterns'][] = 'no_gaji found (line search for numbers)';
                            break 2;
                        }
                    }
                }
            }
        }

        // Extract Bulan (Month/Year)
        if (preg_match('/bulan\s*:?\s*(\d{2}\/\d{4})/i', $cleanText, $matches)) {
            $data['bulan'] = trim($matches[1]);
            $data['debug_patterns'][] = 'bulan found (inline)';
        } elseif (preg_match('/bulan\s+(\d{2}\/\d{4})/i', $cleanText, $matches)) {
            $data['bulan'] = trim($matches[1]);
            $data['debug_patterns'][] = 'bulan found (adjacent)';
        }

                 // Extract salary fields using improved patterns
         
         // Gaji Pokok - tab format: "0001 Gaji Pokok	3,365.73"
         if (preg_match('/0001\s+gaji\s+pokok\s+([\d,]+\.?\d*)/i', $cleanText, $matches)) {
             $data['gaji_pokok'] = (float) str_replace(',', '', $matches[1]);
             $data['debug_patterns'][] = 'gaji_pokok found (tab format)';
         } elseif (preg_match('/gaji\s+pokok\s+([\d,]+\.?\d*)/i', $cleanText, $matches)) {
             $data['gaji_pokok'] = (float) str_replace(',', '', $matches[1]);
             $data['debug_patterns'][] = 'gaji_pokok found (general tab)';
         }
         
         // Jumlah Pendapatan - tab format: "Jumlah Pendapatan	4,926.10"
         if (preg_match('/jumlah\s+pendapatan\s+([\d,]+\.?\d*)/i', $cleanText, $matches)) {
             $data['jumlah_pendapatan'] = (float) str_replace(',', '', $matches[1]);
             $data['debug_patterns'][] = 'jumlah_pendapatan found (tab format)';
         }
         
         // Gaji Bersih - tab format: "Gaji Bersih	1382.8"
         if (preg_match('/gaji\s+bersih\s+([\d,]+\.?\d*)/i', $cleanText, $matches)) {
             $data['gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
             $data['debug_patterns'][] = 'gaji_bersih found (tab format)';
         } elseif (preg_match('/gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i', $cleanText, $matches)) {
             $data['gaji_bersih'] = (float) str_replace(',', '', $matches[1]);
             $data['debug_patterns'][] = 'gaji_bersih found (colon format)';
         }

         // Peratus Gaji Bersih - handle "% Peratus Gaji Bersih	AYSIA	39.86."
         $percentagePatterns = [
             '/%\s*peratus\s+gaji\s+bersih.*?([\d,]+\.?\d*)\s*\.?\s*$/im',
             '/%\s*peratus\s+gaji\s+bersih\s*:\s*([\d,]+\.?\d*)/i',
             '/%\s*peratus\s+gaji\s+bersih.*?([\d,]+\.?\d*)/i',
         ];
         
         foreach ($percentagePatterns as $pattern) {
             if (preg_match($pattern, $cleanText, $matches)) {
                 $rawValue = $matches[1];
                 $cleanValue = rtrim($rawValue, '.');
                 $value = (float) str_replace(',', '', $cleanValue);
                 if ($value >= 10 && $value <= 100) {
                     $data['peratus_gaji_bersih'] = $value;
                     $data['debug_patterns'][] = 'peratus_gaji_bersih found: ' . $value . ' (from: ' . $rawValue . ')';
                     break;
                 }
             }
         }
         
         // Jumlah Potongan - calculate if not found directly
         if (preg_match('/jumlah\s+potongan\s+([\d,]+\.?\d*)/i', $cleanText, $matches)) {
             $data['jumlah_potongan'] = (float) str_replace(',', '', $matches[1]);
             $data['debug_patterns'][] = 'jumlah_potongan found (tab format)';
         } elseif ($data['jumlah_pendapatan'] !== null && $data['gaji_bersih'] !== null) {
             $calculated = $data['jumlah_pendapatan'] - $data['gaji_bersih'];
             if ($calculated >= 0) {
                 $data['jumlah_potongan'] = round($calculated, 2);
                 $data['debug_patterns'][] = 'jumlah_potongan calculated: ' . $data['jumlah_potongan'];
             }
         }

        return $data;
    }
} 