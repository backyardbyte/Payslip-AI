<?php

namespace App\Console\Commands;

use App\Services\WhatsAppBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WhatsAppBotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:bot {action=setup : The action to perform (setup, webhook, test)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage WhatsApp bot operations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        try {
            $botService = new WhatsAppBotService();

            switch ($action) {
                case 'setup':
                    $this->setupBot($botService);
                    break;
                case 'webhook':
                    $this->setupWebhook($botService);
                    break;
                case 'test':
                    $this->testBot($botService);
                    break;
                default:
                    $this->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("WhatsApp bot error: " . $e->getMessage());
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            Log::error('WhatsApp bot command error', [
                'action' => $action,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Set up bot configuration
     */
    private function setupBot(WhatsAppBotService $botService): void
    {
        $this->info('Setting up WhatsApp bot...');
        
        // Check configuration
        $accessToken = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $webhookVerifyToken = config('services.whatsapp.webhook_verify_token');
        
        if (!$accessToken) {
            $this->error('❌ WHATSAPP_ACCESS_TOKEN not configured');
            return;
        }
        
        if (!$phoneNumberId) {
            $this->error('❌ WHATSAPP_PHONE_NUMBER_ID not configured');
            return;
        }
        
        if (!$webhookVerifyToken) {
            $this->error('❌ WHATSAPP_WEBHOOK_VERIFY_TOKEN not configured');
            return;
        }
        
        $this->info('✅ Access token configured');
        $this->info('✅ Phone number ID configured');
        $this->info('✅ Webhook verify token configured');
        
        $botService->setupBot();
        $this->info('✅ Bot setup completed!');
    }

    /**
     * Set up webhook information
     */
    private function setupWebhook(WhatsAppBotService $botService): void
    {
        $webhookUrl = config('services.whatsapp.webhook_url');
        
        if (!$webhookUrl) {
            $this->error('❌ Webhook URL not configured. Please set WHATSAPP_WEBHOOK_URL in your .env file.');
            return;
        }

        $this->info("📡 WhatsApp Webhook Configuration");
        $this->info("================================");
        $this->info("Webhook URL: {$webhookUrl}");
        $this->info("Verify Token: " . config('services.whatsapp.webhook_verify_token'));
        $this->info("");
        
        $this->info("📋 To configure webhook in Meta Developer Console:");
        $this->info("1. Go to https://developers.facebook.com/apps");
        $this->info("2. Select your app and go to WhatsApp > Configuration");
        $this->info("3. Edit webhook and set:");
        $this->info("   - Callback URL: {$webhookUrl}");
        $this->info("   - Verify Token: " . config('services.whatsapp.webhook_verify_token'));
        $this->info("4. Subscribe to 'messages' webhook field");
        $this->info("");
        $this->info("✅ Configuration details provided!");
    }

    /**
     * Test bot functionality
     */
    private function testBot(WhatsAppBotService $botService): void
    {
        $this->info('🧪 Testing WhatsApp bot...');
        
        try {
            $botService->setupBot();
            $this->info('✅ Bot service initialized successfully');
            
            // Test basic configuration
            $accessToken = config('services.whatsapp.access_token');
            $phoneNumberId = config('services.whatsapp.phone_number_id');
            
            if ($accessToken && $phoneNumberId) {
                $this->info('✅ Bot credentials configured correctly');
                
                // Test sending a message to yourself (you can modify this)
                $testPhone = $this->ask('Enter phone number to send test message (with country code, e.g. 60123456789):');
                
                if ($testPhone) {
                    $sent = $botService->sendTextMessage($testPhone, 
                        "🧪 *Test Message*\n\n" .
                        "This is a test message from your WhatsApp bot!\n\n" .
                        "If you receive this, your bot is working correctly. 🎉\n\n" .
                        "Time: " . now()->format('Y-m-d H:i:s')
                    );
                    
                    if ($sent) {
                        $this->info('✅ Test message sent successfully!');
                    } else {
                        $this->error('❌ Failed to send test message');
                    }
                }
            } else {
                $this->error('❌ Bot credentials not configured');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Bot test failed: ' . $e->getMessage());
        }
    }
} 