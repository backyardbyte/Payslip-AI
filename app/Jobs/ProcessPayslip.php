<?php

namespace App\Jobs;

use App\Models\Koperasi;
use App\Models\Payslip;
use App\Services\SettingsService;
use App\Services\PayslipProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;


class ProcessPayslip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(public Payslip $payslip)
    {
        // Get timeout from settings
        $settingsService = app(SettingsService::class);
        $this->timeout = $settingsService->get('advanced.queue_timeout', 300);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get settings service
        $settingsService = app(SettingsService::class);
        $processingService = app(PayslipProcessingService::class);
        
        // Set memory limit from settings
        $memoryLimit = $settingsService->get('advanced.memory_limit', 512);
        ini_set('memory_limit', $memoryLimit . 'M');
        
        // ALWAYS use modern processing - no fallback to legacy
        Log::info('Using modern PayslipProcessingService for payslip ' . $this->payslip->id);
        
        $result = $processingService->processPayslip($this->payslip);
        
        // Send notifications with enhanced data
        $this->sendTelegramNotification($result['detailed_koperasi_results']);
        $this->sendWhatsAppNotification($result['detailed_koperasi_results']);

        // Update batch progress if this payslip is part of a batch
        if ($this->payslip->batch_id) {
            $batchOperation = $this->payslip->batchOperation;
            if ($batchOperation) {
                $batchOperation->updateProgress();
            }
        }
        
        Log::info('Modern payslip processing completed successfully for ID ' . $this->payslip->id);
    }

    /**
     * Perform OCR using available method (OCR.space or Tesseract)
     */
    private function performOCR(string $filePath): string
    {
        // ALWAYS use OCR.space - ignore all settings and env variables
        Log::info('Forcing OCR.space method for payslip ' . $this->payslip->id, [
            'file_path' => $filePath
        ]);
        
        return $this->performOCRSpace($filePath);
    }
    
    /**
     * Perform OCR using OCR.space API
     */
    private function performOCRSpace(string $filePath): string
    {
        $settingsService = app(SettingsService::class);
        
        // Fix API key retrieval - check if settings value is empty and fallback to env
        $settingsApiKey = $settingsService->get('ocr.ocrspace_api_key');
        $apiKey = !empty($settingsApiKey) ? $settingsApiKey : env('OCRSPACE_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('OCR.space API key not configured. Please configure it in settings or .env file');
        }
        
        // Log debug info to understand the source
        Log::info('OCR API key source debug', [
            'payslip_id' => $this->payslip->id,
            'settings_value' => $settingsApiKey ? 'Found (' . strlen($settingsApiKey) . ' chars)' : 'Empty/null',
            'env_value' => env('OCRSPACE_API_KEY') ? 'Found (' . strlen(env('OCRSPACE_API_KEY')) . ' chars)' : 'Empty/null',
            'using_source' => !empty($settingsApiKey) ? 'Settings' : 'Environment',
            'final_key_length' => strlen($apiKey)
        ]);
        
        $debugMode = $settingsService->get('advanced.enable_debug_mode', false);
        
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
                'language' => 'eng', // Start with English only, add Malay later if needed
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true',
                'OCREngine' => '2', // OCR Engine 2 is better for mixed languages
                'isTable' => 'true', // Better for structured documents like payslips
            ];
            
            if ($debugMode) {
                Log::info('Making OCR.space API request', [
                    'payslip_id' => $this->payslip->id,
                    'file_size' => strlen($fileData),
                    'mime_type' => $mimeType
                ]);
            }
            
            // Make API request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ocrTimeout = $settingsService->get('ocr.api_timeout', 120); // Configurable timeout
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
                    'payslip_id' => $this->payslip->id,
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
                Log::info('OCR.space extraction completed', [
                    'payslip_id' => $this->payslip->id,
                    'text_length' => strlen($extractedText),
                    'file_parse_status' => $result['ParsedResults'][0]['FileParseExitCode'] ?? 'unknown'
                ]);
            }
            
            return trim($extractedText);
            
        } catch (\Exception $e) {
            Log::error('OCR.space processing failed for payslip ' . $this->payslip->id, [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            
            // NEVER fall back to Tesseract when using OCR.space
            throw new \Exception('OCR.space processing failed: ' . $e->getMessage() . '. Please check your OCR.space API key configuration.');
        }
    }
    
    /**
     * Perform OCR using local Tesseract
     */
    private function performTesseractOCR(string $filePath): string
    {
        if (!class_exists('thiagoalessio\TesseractOCR\TesseractOCR')) {
            throw new \Exception('Tesseract OCR library not available. Please set OCR_METHOD=ocrspace in your .env file to use cloud OCR.');
        }
        
        try {
            $tesseractClass = 'thiagoalessio\TesseractOCR\TesseractOCR';
            return (new $tesseractClass($filePath))
                ->lang('eng+msa') // English and Malay language support
                ->configFile('bazaar') // Better for mixed text
                ->run();
        } catch (\Exception $e) {
            throw new \Exception('Tesseract OCR failed: ' . $e->getMessage() . '. Consider using OCR.space by setting OCR_METHOD=ocrspace in your .env file.');
        }
    }

    private function checkEligibility(float $peratusGajiBersih, array $rules, array $extractedData): array
    {
        $eligible = true;
        $reasons = [];
        
        // Check maximum percentage of net salary
        if (isset($rules['max_peratus_gaji_bersih'])) {
            if ($peratusGajiBersih > $rules['max_peratus_gaji_bersih']) {
                $eligible = false;
                $reasons[] = "Peratus gaji bersih ({$peratusGajiBersih}%) melebihi had maksimum ({$rules['max_peratus_gaji_bersih']}%)";
            } else {
                $reasons[] = "Peratus gaji bersih ({$peratusGajiBersih}%) memenuhi had maksimum ({$rules['max_peratus_gaji_bersih']}%)";
            }
        }

        // Check minimum basic salary
        if (isset($rules['min_gaji_pokok']) && isset($extractedData['gaji_pokok'])) {
            if ($extractedData['gaji_pokok'] < $rules['min_gaji_pokok']) {
                $eligible = false;
                $reasons[] = "Gaji pokok (RM " . number_format($extractedData['gaji_pokok'], 2) . ") kurang daripada minimum (RM " . number_format($rules['min_gaji_pokok'], 2) . ")";
            } else {
                $reasons[] = "Gaji pokok (RM " . number_format($extractedData['gaji_pokok'], 2) . ") memenuhi minimum (RM " . number_format($rules['min_gaji_pokok'], 2) . ")";
            }
        }

        // Check minimum net salary
        if (isset($rules['min_gaji_bersih']) && isset($extractedData['gaji_bersih'])) {
            if ($extractedData['gaji_bersih'] < $rules['min_gaji_bersih']) {
                $eligible = false;
                $reasons[] = "Gaji bersih (RM " . number_format($extractedData['gaji_bersih'], 2) . ") kurang daripada minimum (RM " . number_format($rules['min_gaji_bersih'], 2) . ")";
            } else {
                $reasons[] = "Gaji bersih (RM " . number_format($extractedData['gaji_bersih'], 2) . ") memenuhi minimum (RM " . number_format($rules['min_gaji_bersih'], 2) . ")";
            }
        }

        // Check maximum age (if available)
        if (isset($rules['max_umur']) && isset($extractedData['umur'])) {
            if ($extractedData['umur'] > $rules['max_umur']) {
                $eligible = false;
                $reasons[] = "Umur ({$extractedData['umur']}) melebihi had maksimum ({$rules['max_umur']})";
            } else {
                $reasons[] = "Umur ({$extractedData['umur']}) memenuhi had maksimum ({$rules['max_umur']})";
            }
        }

        if (empty($reasons)) {
            $reasons[] = "Tiada peraturan khusus - layak secara default";
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons
        ];
    }

    /**
     * Send processing result to Telegram if payslip came from Telegram
     */
    private function sendTelegramNotification(array $detailedKoperasiResults): void
    {
        // Only send notification if payslip came from Telegram
        if ($this->payslip->source !== 'telegram' || !$this->payslip->telegram_chat_id) {
            return;
        }

        // Check if Telegram bot token is configured
        $token = config('services.telegram.bot_token');
        if (!$token) {
            Log::warning("Skipping Telegram notification for payslip {$this->payslip->id}: Bot token not configured");
            return;
        }

        try {
            // Use SimpleTelegramBotService for notifications
            $telegramService = new \App\Services\SimpleTelegramBotService();
            
            // Format eligibility results for Telegram
            $eligibilityResults = [];
            foreach ($detailedKoperasiResults as $koperasiName => $result) {
                $eligibilityResults[] = [
                    'koperasi_name' => $koperasiName,
                    'eligible' => $result['eligible'],
                    'reasons' => $result['reasons']
                ];
            }

            $telegramService->sendProcessingResult($this->payslip, $eligibilityResults);
            
            Log::info("Sent Telegram notification for payslip {$this->payslip->id} to chat {$this->payslip->telegram_chat_id}");

        } catch (\Exception $e) {
            Log::error("Failed to send Telegram notification for payslip {$this->payslip->id}: " . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp notification with results
     */
    private function sendWhatsAppNotification(array $detailedKoperasiResults): void
    {
        // Only send notification if payslip came from WhatsApp
        if ($this->payslip->source !== 'whatsapp' || !$this->payslip->whatsapp_phone) {
            return;
        }

        // Check if WhatsApp bot is configured
        $accessToken = config('services.whatsapp.access_token');
        if (!$accessToken) {
            Log::warning("Skipping WhatsApp notification for payslip {$this->payslip->id}: Access token not configured");
            return;
        }

        try {
            $whatsappService = new \App\Services\WhatsAppBotService();
            
            // Format eligibility results for WhatsApp
            $eligibilityResults = [];
            foreach ($detailedKoperasiResults as $koperasiName => $result) {
                $eligibilityResults[] = [
                    'koperasi_name' => $koperasiName,
                    'eligible' => $result['eligible'],
                    'reasons' => $result['reasons']
                ];
            }

            $whatsappService->sendProcessingResult($this->payslip, $eligibilityResults);
            
            Log::info("Sent WhatsApp notification for payslip {$this->payslip->id} to phone {$this->payslip->whatsapp_phone}");

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp notification for payslip {$this->payslip->id}: " . $e->getMessage());
        }
    }
}
