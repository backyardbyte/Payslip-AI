<?php

namespace App\Services;

use App\Models\Payslip;
use App\Models\User;
use App\Models\Koperasi;
use App\Jobs\ProcessPayslip;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class SimpleTelegramBotService
{
    private BotApi $bot;
    private int $lastUpdateId = 0;
    
    // Simple state management
    private const STATE_NONE = 'none';
    private const STATE_WAITING_FILE = 'waiting_file';
    
    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            throw new \Exception('Telegram bot token not configured');
        }
        $this->bot = new BotApi($token);
    }
    
    public function run(): void
    {
        Log::info('Starting simple Telegram bot...');
        
        $consecutiveErrors = 0;
        $maxErrors = 5;
        
        while (true) {
            try {
                $updates = $this->bot->getUpdates($this->lastUpdateId + 1, 100, 10); // Increased timeout to 10 seconds
                
                foreach ($updates as $update) {
                    $this->processUpdate($update);
                    $this->lastUpdateId = $update->getUpdateId();
                }
                
                // Reset error counter on successful polling
                $consecutiveErrors = 0;
                usleep(500000); // 500ms delay between polls
                
            } catch (\Exception $e) {
                $consecutiveErrors++;
                Log::error("Bot error: " . $e->getMessage());
                
                if ($consecutiveErrors >= $maxErrors) {
                    Log::critical("Too many consecutive errors in Telegram bot, stopping...");
                    break;
                }
                
                // Exponential backoff
                $backoffTime = min(30, pow(2, $consecutiveErrors));
                Log::info("Backing off for {$backoffTime} seconds after error #{$consecutiveErrors}");
                sleep($backoffTime);
            }
        }
    }
    
    private function processUpdate(Update $update): void
    {
        try {
            // Handle callback queries
            if ($update->getCallbackQuery()) {
                $this->handleCallbackQuery($update->getCallbackQuery());
                return;
            }
            
            $message = $update->getMessage();
            if (!$message) {
                return;
            }
            
            $chatId = $message->getChat()->getId();
            $text = $message->getText();
            
            // Handle document uploads
            if ($message->getDocument()) {
                $this->handleDocument($chatId, $message);
                return;
            }
            
            // Handle photo uploads
            if ($message->getPhoto()) {
                $this->handlePhoto($chatId, $message);
                return;
            }
            
            // Handle commands
            if ($text && str_starts_with($text, '/')) {
                $command = explode(' ', $text)[0];
                $this->handleCommand($chatId, $command, $message);
                return;
            }
            
            // Handle regular messages based on state
            $state = $this->getState($chatId);
            if ($state === self::STATE_WAITING_FILE) {
                $this->sendMessage($chatId, "⏳ I'm waiting for your payslip file. Please send a file or use /cancel to abort.");
                return;
            }
            
            // Handle menu button presses
            $this->handleMenuButton($chatId, $text, $message);
            
        } catch (\Exception $e) {
            Log::error("Error processing update: " . $e->getMessage());
        }
    }
    
    private function handleCommand(int $chatId, string $command, Message $message): void
    {
        switch ($command) {
            case '/start':
                $this->handleStart($chatId);
                break;
                
            case '/scan':
                $this->handleScan($chatId);
                break;
                
            case '/koperasi':
                $this->handleKoperasi($chatId);
                break;
                
            case '/status':
                $this->handleStatus($chatId, $message);
                break;
                
            case '/help':
                $this->handleHelp($chatId);
                break;
                
            case '/cancel':
                $this->handleCancel($chatId);
                break;
                
            default:
                $this->sendMessage($chatId, "❌ Unknown command. Use /help to see available commands.");
        }
    }
    
    private function handleMenuButton(int $chatId, string $text, Message $message): void
    {
        switch ($text) {
            case '📄 Scan Payslip':
                $this->handleScan($chatId);
                break;
                
            case '🏦 Koperasi List':
                $this->handleKoperasi($chatId);
                break;
                
            case '📊 Check Status':
                $this->handleStatus($chatId, $message);
                break;
                
            case '❓ Help':
                $this->handleHelp($chatId);
                break;
                
            default:
                $this->sendMessage($chatId, "🤔 I don't understand that. Please use the menu buttons or /help for available commands.");
        }
    }
    
    private function handleStart(int $chatId): void
    {
        $text = "🏦 *Welcome to Payslip AI!*\n\n";
        $text .= "I am an intelligent bot that helps you analyze payslips and check koperasi eligibility.\n\n";
        $text .= "Choose the operation you want to perform:";
        
        $keyboard = new ReplyKeyboardMarkup([
            ['📄 Scan Payslip', '🏦 Koperasi List'],
            ['📊 Check Status', '❓ Help']
        ], true, false);
        
        $this->sendMessage($chatId, $text, 'Markdown', $keyboard);
    }
    
    private function handleScan(int $chatId): void
    {
        $this->setState($chatId, self::STATE_WAITING_FILE);
        
        $text = "📄 *Scan Payslip*\n\n";
        $text .= "Send your payslip in supported format for automatic analysis.\n\n";
        $text .= "📋 *Supported formats:*\n";
        $text .= "• PDF (recommended)\n";
        $text .= "• JPG, PNG, JPEG\n";
        $text .= "• Maximum: 20MB\n\n";
        $text .= "💡 *Tips for best results:*\n";
        $text .= "• Ensure text is clear and not blurry\n";
        $text .= "• Use good lighting\n";
        $text .= "• Make sure all information is visible\n\n";
        $text .= "📤 Send your file now...";
        
        // Create inline keyboard with cancel button
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '❌ Cancel', 'callback_data' => 'cancel_scan']]
        ]);
        
        $this->sendMessage($chatId, $text, 'Markdown', $keyboard);
    }
    
    private function handleKoperasi(int $chatId): void
    {
        try {
            $koperasiList = Koperasi::where('is_active', true)->orderBy('name')->get();
            
            $text = "🏦 *Koperasi List*\n\n";
            $text .= "Here are the active koperasi institutions:\n\n";
            
            if ($koperasiList->isEmpty()) {
                $text .= "No active koperasi found in the system.\n";
            } else {
                foreach ($koperasiList as $index => $koperasi) {
                    $text .= ($index + 1) . "️⃣ *{$koperasi->name}*\n";
                    
                    $rules = $koperasi->rules;
                    if (isset($rules['max_peratus_gaji_bersih'])) {
                        $text .= "   • Max: {$rules['max_peratus_gaji_bersih']}% of net salary\n";
                    }
                    if (isset($rules['min_gaji_pokok'])) {
                        $text .= "   • Min salary: RM " . number_format($rules['min_gaji_pokok']) . "\n";
                    }
                    if (isset($rules['max_umur'])) {
                        $text .= "   • Max age: {$rules['max_umur']} years\n";
                    }
                    $text .= "\n";
                }
            }
            
            $text .= "💡 Send your payslip to check eligibility automatically!";
            
            $this->sendMessage($chatId, $text, 'Markdown');
        } catch (\Exception $e) {
            Log::error("Error fetching koperasi: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Error loading koperasi list. Please try again later.");
        }
    }
    
    private function handleStatus(int $chatId, Message $message): void
    {
        try {
            $user = $this->getOrCreateUser($message);
            
            $recentPayslips = Payslip::where('user_id', $user->id)
                ->where('source', 'telegram')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
            
            $text = "📊 *Processing Status*\n\n";
            
            if ($recentPayslips->isEmpty()) {
                $text .= "You haven't processed any payslips yet.\n\n";
                $text .= "Use /scan to start analyzing your payslips!";
            } else {
                $text .= "Your recent payslips:\n\n";
                
                foreach ($recentPayslips as $payslip) {
                    $statusIcon = $this->getStatusIcon($payslip->status);
                    $text .= "{$statusIcon} *Payslip #{$payslip->id}*\n";
                    $text .= "📅 " . $payslip->created_at->format('d/m/Y H:i') . "\n";
                    $text .= "📋 Status: " . ucfirst($payslip->status) . "\n";
                    
                    if ($payslip->status === 'completed' && !empty($payslip->extracted_data)) {
                        $data = $payslip->extracted_data;
                        if (isset($data['gaji_bersih'])) {
                            $text .= "💰 Net Salary: RM " . number_format($data['gaji_bersih'], 2) . "\n";
                        }
                    }
                    $text .= "\n";
                }
            }
            
            $this->sendMessage($chatId, $text, 'Markdown');
        } catch (\Exception $e) {
            Log::error("Error fetching status: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Error loading status. Please try again later.");
        }
    }
    
    private function handleHelp(int $chatId): void
    {
        $text = "🆘 *Bot Commands*\n\n";
        $text .= "/start - Start the bot\n";
        $text .= "/scan - Scan a payslip\n";
        $text .= "/koperasi - View koperasi list\n";
        $text .= "/status - Check processing status\n";
        $text .= "/help - Show this help\n";
        $text .= "/cancel - Cancel current operation\n\n";
        $text .= "💡 You can also use the menu buttons!";
        
        $this->sendMessage($chatId, $text, 'Markdown');
    }
    
    private function handleCancel(int $chatId): void
    {
        $this->setState($chatId, self::STATE_NONE);
        $this->sendMessage($chatId, "❌ Operation cancelled.");
        $this->handleStart($chatId);
    }
    
    private function handleDocument(int $chatId, Message $message): void
    {
        $state = $this->getState($chatId);
        
        if ($state !== self::STATE_WAITING_FILE) {
            $this->sendMessage($chatId, "📎 To scan this document, please use /scan first.");
            return;
        }
        
        try {
            $document = $message->getDocument();
            $fileId = $document->getFileId();
            $fileName = $document->getFileName() ?? 'document.pdf';
            
            // Check file size (20MB limit)
            if ($document->getFileSize() > 20 * 1024 * 1024) {
                $this->sendMessage($chatId, "❌ File too large. Maximum size is 20MB.");
                return;
            }
            
            $this->processFileUpload($chatId, $fileId, $fileName, $message);
            
        } catch (\Exception $e) {
            Log::error("Error handling document: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Error processing document. Please try again.");
        }
    }
    
    private function handlePhoto(int $chatId, Message $message): void
    {
        $state = $this->getState($chatId);
        
        if ($state !== self::STATE_WAITING_FILE) {
            $this->sendMessage($chatId, "📷 To scan this photo, please use /scan first.");
            return;
        }
        
        try {
            $photos = $message->getPhoto();
            if (empty($photos)) {
                $this->sendMessage($chatId, "❌ No photo found in message.");
                return;
            }
            
            // Get the largest photo
            $photo = end($photos);
            $fileId = $photo->getFileId();
            $fileName = 'photo_' . time() . '.jpg';
            
            $this->processFileUpload($chatId, $fileId, $fileName, $message);
            
        } catch (\Exception $e) {
            Log::error("Error handling photo: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Error processing photo. Please try again.");
        }
    }
    
    private function processFileUpload(int $chatId, string $fileId, string $fileName, Message $message): void
    {
        try {
            $this->setState($chatId, self::STATE_NONE);
            
            // Send processing message
            $this->sendMessage($chatId, "⚙️ Processing your payslip... This may take a few minutes.");
            
            // Download file from Telegram
            $fileInfo = $this->bot->getFile($fileId);
            $filePath = $fileInfo->getFilePath();
            
            $fileUrl = "https://api.telegram.org/file/bot" . config('services.telegram.bot_token') . "/{$filePath}";
            
            $response = Http::get($fileUrl);
            if (!$response->successful()) {
                throw new \Exception('Failed to download file from Telegram');
            }
            
            // Save file to storage
            $storagePath = 'payslips/telegram/' . time() . '_' . $fileName;
            Storage::put($storagePath, $response->body());
            
            // Get or create user
            $user = $this->getOrCreateUser($message);
            
            // Create payslip record
            $payslip = Payslip::create([
                'user_id' => $user->id,
                'file_path' => $storagePath,
                'original_filename' => $fileName,
                'status' => 'uploaded',
                'source' => 'telegram',
                'telegram_chat_id' => $chatId,
                'processing_priority' => 1,
                'extracted_data' => [
                    'telegram_chat_id' => $chatId,
                    'telegram_user_id' => $message->getFrom()->getId(),
                    'telegram_username' => $message->getFrom()->getUsername(),
                    'uploaded_via' => 'telegram_bot',
                ],
            ]);
            
            // Process payslip using the new mode-aware service
            $processingService = app(\App\Services\PayslipProcessingService::class);
            $result = $processingService->processPayslipWithMode($payslip);
            
            $text = "✅ Your payslip has been uploaded successfully!\n\n";
            $text .= "📋 Payslip ID: #{$payslip->id}\n";
            $text .= "🔄 Processing Status: " . ($result['status'] ?? 'completed') . "\n";
            $text .= "⏳ Status: Processing\n\n";
            $text .= "You will receive the results here when processing is complete. This usually takes 1-3 minutes.\n\n";
            $text .= "Use /status to check your recent payslips.";
            
            $this->sendMessage($chatId, $text, 'Markdown');
            
            Log::info("Payslip uploaded via Telegram", [
                'payslip_id' => $payslip->id,
                'chat_id' => $chatId,
                'user_id' => $user->id,
                'filename' => $fileName
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error processing file upload: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Error processing file. Please try again or contact support.");
        }
    }
    
    private function getOrCreateUser(Message $message): User
    {
        $telegramUser = $message->getFrom();
        $telegramUserId = $telegramUser->getId();
        
        // Check if user exists with this telegram_user_id
        $user = User::where('telegram_user_id', $telegramUserId)->first();
        
        if (!$user) {
            // Create a new user
            $name = trim($telegramUser->getFirstName() . ' ' . $telegramUser->getLastName());
            if (empty($name)) {
                $name = $telegramUser->getUsername() ?: 'Telegram User';
            }
            
            $user = User::create([
                'name' => $name,
                'email' => $telegramUserId . '@telegram.bot',
                'password' => bcrypt(str()->random(32)),
                'telegram_user_id' => $telegramUserId,
                'telegram_username' => $telegramUser->getUsername(),
                'role_id' => 1, // Default role
                'is_active' => true,
            ]);
            
            Log::info("Created new user for Telegram bot", [
                'user_id' => $user->id,
                'telegram_user_id' => $telegramUserId,
                'name' => $name
            ]);
        }
        
        return $user;
    }
    
    private function handleCallbackQuery($callbackQuery): void
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        
        try {
            $this->bot->answerCallbackQuery($callbackQuery->getId());
            
            if ($data === 'cancel_scan') {
                $this->setState($chatId, self::STATE_NONE);
                $this->sendMessage($chatId, "❌ Scan cancelled. Returning to main menu.");
                $this->handleStart($chatId);
            } elseif (str_starts_with($data, 'contact_advisor_')) {
                $payslipId = str_replace('contact_advisor_', '', $data);
                $this->handleContactAdvisor($chatId, $payslipId);
            }
        } catch (\Exception $e) {
            Log::error("Callback error: " . $e->getMessage());
        }
    }
    
    private function sendMessage(int $chatId, string $text, string $parseMode = null, $replyMarkup = null): void
    {
        try {
            $this->bot->sendMessage($chatId, $text, $parseMode, false, null, $replyMarkup);
        } catch (\Exception $e) {
            Log::error("Failed to send message: " . $e->getMessage());
        }
    }
    
    private function getState(int $chatId): string
    {
        return Cache::get("bot_state_{$chatId}", self::STATE_NONE);
    }
    
    private function setState(int $chatId, string $state): void
    {
        Cache::put("bot_state_{$chatId}", $state, 3600);
    }
    
    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'uploaded' => '📤',
            'processing' => '⚙️',
            'completed' => '✅',
            'failed' => '❌',
            default => '❓'
        };
    }

    /**
     * Handle contact advisor request
     */
    private function handleContactAdvisor(int $chatId, string $payslipId): void
    {
        $text = "👨‍💼 *Financial Advisor Contact*\n\n";
        $text .= "🎉 Great choice! Our financial advisor is ready to help you with your koperasi application.\n\n";
        $text .= "📞 *Contact Information:*\n";
        $text .= "• Phone: +60 12-345 6789\n";
        $text .= "• WhatsApp: +60 12-345 6789\n";
        $text .= "• Email: advisor@weclaim.com\n";
        $text .= "• Telegram: @WeclaimAdvisor\n\n";
        $text .= "🕒 *Office Hours:*\n";
        $text .= "• Monday - Friday: 9:00 AM - 6:00 PM\n";
        $text .= "• Saturday: 9:00 AM - 1:00 PM\n";
        $text .= "• Sunday: Closed\n\n";
        $text .= "💡 *What to mention:*\n";
        $text .= "• Your Payslip ID: #{$payslipId}\n";
        $text .= "• That you're eligible for koperasi application\n";
        $text .= "• Your preferred koperasi choice\n\n";
        $text .= "Our advisor will guide you through the application process and help you get the best terms! 🚀";

        // Add contact buttons
        $inlineKeyboard = new InlineKeyboardMarkup([
            [
                ['text' => '📞 Call Now', 'url' => 'tel:+60123456789'],
                ['text' => '💬 WhatsApp', 'url' => 'https://wa.me/60123456789']
            ],
            [
                ['text' => '📧 Send Email', 'url' => 'mailto:advisor@weclaim.com'],
                ['text' => '📱 Telegram', 'url' => 'https://t.me/WeclaimAdvisor']
            ]
        ]);

        $this->sendMessage($chatId, $text, 'Markdown', $inlineKeyboard);
    }
    
    /**
     * Send processing results to Telegram user
     */
    public function sendProcessingResult(Payslip $payslip, array $eligibilityResults): void
    {
        try {
            $chatId = $payslip->telegram_chat_id;
            if (!$chatId) {
                Log::warning("No telegram_chat_id found for payslip {$payslip->id}");
                return;
            }
            
            $text = "";
            
            if ($payslip->status === 'failed') {
                $text = "❌ *Processing Failed*\n\n";
                $text .= "📋 Payslip ID: #{$payslip->id}\n";
                $text .= "⚠️ Error: Processing failed. Please try uploading again or contact support.\n\n";
                $text .= "💡 Make sure your payslip image is clear and all text is visible.";
            } else {
                $extractedData = $payslip->extracted_data ?? [];
                
                $text = "✅ *Payslip Analysis Complete!*\n\n";
                $text .= "📋 Payslip ID: #{$payslip->id}\n";
                $text .= "📅 Processed: " . $payslip->processing_completed_at->format('d/m/Y H:i') . "\n\n";
                
                // Add extracted salary information
                if (isset($extractedData['gaji_bersih'])) {
                    $text .= "💰 *Salary Information:*\n";
                    $text .= "• Net Salary: RM " . number_format($extractedData['gaji_bersih'], 2) . "\n";
                    
                    if (isset($extractedData['gaji_pokok'])) {
                        $text .= "• Basic Salary: RM " . number_format($extractedData['gaji_pokok'], 2) . "\n";
                    }
                    
                    if (isset($extractedData['peratus_gaji_bersih'])) {
                        $text .= "• Percentage Used: " . number_format($extractedData['peratus_gaji_bersih'], 2) . "%\n";
                    }
                    
                    if (isset($extractedData['bulan'])) {
                        $text .= "• Month: " . $extractedData['bulan'] . "\n";
                    }
                    $text .= "\n";
                }
                
                // Filter and show only eligible koperasi
                if (!empty($eligibilityResults)) {
                    $eligibleKoperasi = collect($eligibilityResults)->filter(function($result) {
                        return $result['eligible'] === true;
                    })->toArray();
                    
                    if (!empty($eligibleKoperasi)) {
                        $eligibleCount = count($eligibleKoperasi);
                        $text .= "🎉 *Great News! You are eligible for koperasi:*\n\n";
                        
                        foreach ($eligibleKoperasi as $result) {
                            $text .= "✅ *{$result['koperasi_name']}*\n";
                            $text .= "   🎯 Status: You qualify for this koperasi!\n";
                            
                            // Show positive reasons
                            if (!empty($result['reasons'])) {
                                $mainReason = $result['reasons'][0];
                                $text .= "   💡 " . $mainReason . "\n";
                            }
                            $text .= "\n";
                        }
                        
                        $text .= "📊 *Summary:* You are eligible for {$eligibleCount} koperasi\n\n";
                        $text .= "🚀 Ready to take the next step? Our financial advisor can help you with the application process!";
                    } else {
                        $text .= "😔 *No Eligible Koperasi Found*\n\n";
                        $text .= "Unfortunately, based on your current payslip data, you don't meet the eligibility requirements for any koperasi at this time.\n\n";
                        $text .= "💡 *Tips to improve eligibility:*\n";
                        $text .= "• Check if your salary percentage is within required limits\n";
                        $text .= "• Ensure all payslip data was extracted correctly\n";
                        $text .= "• Consider trying again next month if your salary changes\n\n";
                    }
                } else {
                    $text .= "⚠️ Unable to check koperasi eligibility. Please check if the payslip data was extracted correctly.\n\n";
                    $eligibleKoperasi = []; // Initialize for inline keyboard logic
                }
                
                $text .= "\n💡 Use /status to view your payslip history or /scan to analyze another payslip.";
            }
            
            // Add inline keyboard for contacting financial advisor if eligible for any koperasi
            $replyMarkup = null;
            if (!empty($eligibleKoperasi)) {
                $inlineKeyboard = new InlineKeyboardMarkup([
                    [
                        ['text' => '💬 Contact Financial Advisor', 'callback_data' => 'contact_advisor_' . $payslip->id]
                    ]
                ]);
                $replyMarkup = $inlineKeyboard;
            }
            
            $this->sendMessage($chatId, $text, 'Markdown', $replyMarkup);
            
            Log::info("Sent processing result to Telegram", [
                'payslip_id' => $payslip->id,
                'chat_id' => $chatId,
                'status' => $payslip->status
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram processing result: " . $e->getMessage(), [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage()
            ]);
        }
    }
} 