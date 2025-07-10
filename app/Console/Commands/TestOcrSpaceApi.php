<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestOcrSpaceApi extends Command
{
    protected $signature = 'ocr:test-api {--show-key : Show the API key (for debugging)}';
    protected $description = 'Test OCR.space API configuration and connection';

    public function handle()
    {
        $this->info('Testing OCR.space API Configuration');
        $this->line('=====================================');

        $settingsService = app(SettingsService::class);
        
        // Fix API key retrieval - check if settings value is empty and fallback to env
        $settingsApiKey = $settingsService->get('ocr.ocrspace_api_key');
        $apiKey = !empty($settingsApiKey) ? $settingsApiKey : env('OCRSPACE_API_KEY');

        // Check if API key is configured
        $this->info('1. API Key Configuration:');
        if (!$apiKey) {
            $this->error('   ❌ OCR.space API key not configured');
            $this->line('   Please set OCRSPACE_API_KEY in your .env file or configure it in settings');
            $this->line('   Get your API key from: https://ocr.space/ocrapi');
            return 1;
        }

        $this->info('   ✅ API key is configured');
        $this->line('   Key length: ' . strlen($apiKey) . ' characters');
        
        if ($this->option('show-key')) {
            $this->line('   Key: ' . $apiKey);
        } else {
            $this->line('   Key preview: ' . substr($apiKey, 0, 8) . '...' . substr($apiKey, -4));
        }

        // Debug info to understand source
        $this->info('2. API Key Source Debug:');
        $envValue = env('OCRSPACE_API_KEY');
        
        $this->line('   From settings: ' . ($settingsApiKey ? 'Found (' . strlen($settingsApiKey) . ' chars)' : 'Not found/empty'));
        $this->line('   From .env: ' . ($envValue ? 'Found (' . strlen($envValue) . ' chars)' : 'Not found/empty'));
        $this->line('   Using: ' . (!empty($settingsApiKey) ? 'Settings value' : 'Environment value'));

        // Test API connection with a simple request
        $this->info('3. API Connection Test:');
        $this->line('   Testing connection to OCR.space API...');
        
        try {
            // Create a minimal test image (1x1 pixel PNG) - base64 encoded
            $testImageBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
            
            $postData = [
                'apikey' => $apiKey,
                'base64Image' => 'data:image/png;base64,' . $testImageBase64,
                // Remove language parameter for better compatibility with free API keys
                'isOverlayRequired' => 'false',
                'OCREngine' => '2',
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Payslip-AI/1.0');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $this->error('   ❌ Connection failed: ' . $curlError);
                return 1;
            }

            if ($httpCode !== 200) {
                $this->error('   ❌ HTTP Error: ' . $httpCode);
                $this->line('   Response: ' . substr($response, 0, 200));
                return 1;
            }

            $result = json_decode($response, true);
            if (!$result) {
                $this->error('   ❌ Invalid JSON response');
                $this->line('   Response: ' . substr($response, 0, 200));
                return 1;
            }

            // Check for API errors
            if (isset($result['OCRExitCode']) && $result['OCRExitCode'] != 1) {
                $this->error('   ❌ OCR.space API Error (Exit Code: ' . $result['OCRExitCode'] . ')');
                if (isset($result['ErrorMessage'])) {
                    $errorMsg = is_array($result['ErrorMessage']) ? implode(', ', $result['ErrorMessage']) : $result['ErrorMessage'];
                    $this->line('   Error: ' . $errorMsg);
                }
                return 1;
            }

            $this->info('   ✅ API connection successful');
            $this->line('   Response received with exit code: ' . ($result['OCRExitCode'] ?? 'unknown'));

        } catch (\Exception $e) {
            $this->error('   ❌ Test failed: ' . $e->getMessage());
            return 1;
        }

        // Test settings
        $this->info('4. OCR Settings:');
        $ocrMethod = $settingsService->get('ocr.method', env('OCR_METHOD', 'ocrspace'));
        $ocrTimeout = $settingsService->get('ocr.api_timeout', 120);
        
        $this->line('   OCR Method: ' . $ocrMethod);
        $this->line('   API Timeout: ' . $ocrTimeout . ' seconds');

        if ($ocrMethod !== 'ocrspace') {
            $this->warn('   ⚠️  OCR method is not set to "ocrspace"');
            $this->line('   Current method: ' . $ocrMethod);
        }

        $this->line('');
        $this->info('✅ All tests passed! OCR.space API is properly configured.');
        $this->line('');
        $this->line('Next steps:');
        $this->line('- Try uploading a payslip to test the full OCR processing');
        $this->line('- Check your application logs if you encounter issues');
        $this->line('- Ensure your OCR.space account has sufficient credits');

        return 0;
    }
}