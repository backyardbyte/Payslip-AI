<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ShowOcrText extends Command
{
    protected $signature = 'payslip:show-ocr-text {--payslip-id= : Specific payslip ID}';
    protected $description = 'Display the full OCR extracted text from a payslip';

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

        $this->info("Payslip ID: {$payslip->id}");
        $this->info("File: {$payslip->file_path}");
        
        $path = Storage::path($payslip->file_path);
        
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }
        
        try {
            $text = $this->performOCRSpace($path);
            
            $this->info("================== FULL OCR TEXT ==================");
            $this->line($text);
            $this->info("================= END OCR TEXT ===================");
            
            $this->info("Text length: " . strlen($text));
            $this->info("Line count: " . substr_count($text, "\n"));
            
        } catch (\Exception $e) {
            $this->error("OCR extraction failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
    
    private function performOCRSpace(string $filePath): string
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('OCR.space API returned HTTP ' . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['ParsedResults'])) {
            throw new \Exception('Invalid OCR.space response');
        }
        
        $extractedText = '';
        foreach ($result['ParsedResults'] as $parsedResult) {
            if (isset($parsedResult['ParsedText'])) {
                $extractedText .= $parsedResult['ParsedText'] . "\n";
            }
        }
        
        return trim($extractedText);
    }
} 