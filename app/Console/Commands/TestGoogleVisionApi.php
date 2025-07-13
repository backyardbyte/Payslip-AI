<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestGoogleVisionApi extends Command
{
    protected $signature = 'test:google-vision {--key= : Override API key for testing}';
    protected $description = 'Test Google Vision API configuration and connectivity';

    public function handle()
    {
        $this->info('🔍 Testing Google Vision API Configuration');
        $this->info('=========================================');
        
        // Check API key
        $apiKey = $this->option('key') ?: env('GOOGLE_VISION_API_KEY');
        
        if (!$apiKey) {
            $this->error('❌ Google Vision API key not found!');
            $this->line('   Please set GOOGLE_VISION_API_KEY in your .env file');
            $this->line('   Or use --key option to test with a specific key');
            return 1;
        }
        
        $this->info('✅ API key found (length: ' . strlen($apiKey) . ' characters)');
        
        // Test API connectivity with a simple request
        try {
            $this->info('🌐 Testing API connectivity...');
            
            // Create a simple test image (1x1 pixel white PNG)
            $testImageBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
            
            $postData = [
                'requests' => [
                    [
                        'image' => [
                            'content' => $testImageBase64
                        ],
                        'features' => [
                            [
                                'type' => 'TEXT_DETECTION',
                                'maxResults' => 1
                            ]
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
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $this->error('❌ Network error: ' . $curlError);
                return 1;
            }
            
            $this->info("   HTTP Status: {$httpCode}");
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                
                if ($result && !isset($result['error'])) {
                    $this->info('✅ API connectivity successful!');
                    $this->info('✅ API key is valid and working');
                    
                    // Show configuration recommendations
                    $this->info("\n📋 Configuration Summary:");
                    $this->line("   ✅ API key configured");
                    $this->line("   ✅ Network connectivity working");
                    $this->line("   ✅ Google Vision API enabled");
                    $this->line("   ✅ Ready for payslip processing");
                    
                    $this->info("\n🎉 Google Vision API is fully configured and ready!");
                    
                    return 0;
                } else {
                    $error = $result['error']['message'] ?? 'Unknown error';
                    $this->error('❌ API Error: ' . $error);
                    
                    if (strpos($error, 'API key not valid') !== false) {
                        $this->line('   Your API key appears to be invalid');
                        $this->line('   Please check: https://console.cloud.google.com/apis/credentials');
                    } elseif (strpos($error, 'Cloud Vision API has not been used') !== false) {
                        $this->line('   Cloud Vision API is not enabled for your project');
                        $this->line('   Please enable it at: https://console.cloud.google.com/apis/library/vision.googleapis.com');
                    }
                    
                    return 1;
                }
            } else {
                $this->error("❌ HTTP Error {$httpCode}");
                $this->line('   Response: ' . substr($response, 0, 200));
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return 1;
        }
    }
}
