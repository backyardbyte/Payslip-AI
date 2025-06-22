<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\User;
use App\Models\Payslip;
use App\Jobs\ProcessPayslip;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class TelegramBotService
{
    private BotApi $bot;
    private string $baseApiUrl;
    private int $lastUpdateId = 0;
    private array $commandHandlers = [];
    private array $messageHandlers = [];
    private array $documentHandlers = [];
    private array $photoHandlers = [];

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            throw new \Exception('Telegram bot token not configured');
        }

        $this->bot = new BotApi($token);
        $this->baseApiUrl = config('app.url') . '/api/telegram';
    }

    /**
     * Set up bot commands and handlers
     */
    public function setupBot(): void
    {
        // Register command handlers
        $this->commandHandlers['start'] = function ($message) {
            Log::info('Received /start command');
            $this->handleStartCommand($message);
        };

        $this->commandHandlers['help'] = function ($message) {
            Log::info('Received /help command');
            $this->handleHelpCommand($message);
        };

        $this->commandHandlers['scan'] = function ($message) {
            Log::info('Received /scan command');
            $this->handleScanCommand($message);
        };

        $this->commandHandlers['koperasi'] = function ($message) {
            Log::info('Received /koperasi command');
            $this->handleKoperasiCommand($message);
        };

        $this->commandHandlers['status'] = function ($message) {
            Log::info('Received /status command');
            $this->handleStatusCommand($message);
        };

        // Register message handler
        $this->messageHandlers[] = function ($message) {
            if ($message->getText() && !str_starts_with($message->getText(), '/')) {
                Log::info('Received message: ' . $message->getText());
                $this->handleTextMessage($message);
            }
        };

        // Register document handler
        $this->documentHandlers[] = function ($message) {
            Log::info('Received document upload');
            $this->handleDocumentUpload($message);
        };

        // Register photo handler
        $this->photoHandlers[] = function ($message) {
            Log::info('Received photo upload');
            $this->handlePhotoUpload($message);
        };
    }

    /**
     * Start polling for updates
     */
    public function run(): void
    {
        Log::info('Starting Telegram bot polling...');
        
        while (true) {
            try {
                // Get updates from Telegram
                $updates = $this->bot->getUpdates($this->lastUpdateId + 1, 100, 1);
                
                foreach ($updates as $update) {
                    $this->processUpdate($update);
                    $this->lastUpdateId = $update->getUpdateId();
                }
                
                // Small delay to avoid overwhelming the API
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                Log::error('Error in polling loop: ' . $e->getMessage());
                sleep(1); // Wait longer on error
            }
        }
    }

    /**
     * Process a single update
     */
    private function processUpdate(Update $update): void
    {
        // Handle callback queries (inline keyboard button presses)
        if ($update->getCallbackQuery()) {
            $this->handleCallbackQuery($update->getCallbackQuery());
            return;
        }
        
        $message = $update->getMessage();
        
        if (!$message) {
            return;
        }

        // Handle document uploads
        if ($message->getDocument()) {
            foreach ($this->documentHandlers as $handler) {
                $handler($message);
            }
            return;
        }

        // Handle photo uploads
        if ($message->getPhoto()) {
            foreach ($this->photoHandlers as $handler) {
                $handler($message);
            }
            return;
        }

        $text = $message->getText();
        
        // Handle commands
        if ($text && str_starts_with($text, '/')) {
            $commandName = substr(explode(' ', $text)[0], 1); // Remove '/' and get command name
            
            if (isset($this->commandHandlers[$commandName])) {
                $handler = $this->commandHandlers[$commandName];
                $handler($message);
                return;
            }
        }
        
        // Handle regular messages
        foreach ($this->messageHandlers as $handler) {
            $handler($message);
        }
    }

    /**
     * Handle /start command
     */
    public function handleStartCommand(Message $message): void
    {
        $chatId = $message->getChat()->getId();
        $user = $message->getFrom();
        
        $text = "🏦 *Selamat datang ke Koperasi Bot!*\n\n";
        $text .= "Saya adalah bot yang membantu anda:\n";
        $text .= "✅ Menganalisis slip gaji\n";
        $text .= "📊 Menyemak kelayakan koperasi\n";
        $text .= "💰 Mencari koperasi terbaik\n\n";
        $text .= "*Arahan Utama:*\n";
        $text .= "/scan - Mula mengimbas slip gaji\n";
        $text .= "/koperasi - Lihat senarai koperasi\n";
        $text .= "/status - Semak status pemprosesan\n";
        $text .= "/help - Bantuan lengkap\n\n";
        $text .= "Untuk mula, gunakan /scan atau hantar slip gaji terus! 📄";

        $keyboard = new ReplyKeyboardMarkup([
            ['📄 Imbas Slip Gaji', '🏦 Senarai Koperasi'],
            ['📊 Semak Status', '❓ Bantuan']
        ]);
        $keyboard->setResizeKeyboard(true);
        $keyboard->setOneTimeKeyboard(false);

        try {
            $this->bot->sendMessage($chatId, $text, 'Markdown', false, null, $keyboard);
            Log::info('Sent welcome message to chat: ' . $chatId);
            
            // Register user if not exists
            $this->registerUserIfNotExists($user);
        } catch (\Exception $e) {
            Log::error('Error sending welcome message: ' . $e->getMessage());
        }
    }

    /**
     * Handle /help command
     */
    public function handleHelpCommand(Message $message): void
    {
        $chatId = $message->getChat()->getId();

        $text = "🆘 *Panduan Penggunaan Bot*\n\n";
        $text .= "*Arahan Utama:*\n";
        $text .= "/start - Mula menggunakan bot\n";
        $text .= "/scan - Imbas slip gaji\n";
        $text .= "/koperasi - Lihat senarai koperasi\n";
        $text .= "/status - Semak status pemprosesan\n\n";
        $text .= "*Cara Mengimbas Slip Gaji:*\n";
        $text .= "1️⃣ Gunakan /scan atau tekan butang 'Imbas Slip Gaji'\n";
        $text .= "2️⃣ Hantar fail slip gaji (PDF/gambar)\n";
        $text .= "3️⃣ Tunggu analisis selesai\n";
        $text .= "4️⃣ Dapatkan laporan kelayakan koperasi\n\n";
        $text .= "*Format Fail Disokong:*\n";
        $text .= "📄 PDF (disyorkan)\n";
        $text .= "📷 JPG, PNG, JPEG\n";
        $text .= "📏 Maksimum 20MB\n\n";
        $text .= "Hantar slip gaji anda sekarang untuk analisis automatik! 🚀";

        try {
            $this->bot->sendMessage($chatId, $text, 'Markdown');
            Log::info('Sent help message to chat: ' . $chatId);
        } catch (\Exception $e) {
            Log::error('Error sending help message: ' . $e->getMessage());
        }
    }

    /**
     * Handle /scan command
     */
    public function handleScanCommand(Message $message): void
    {
        $chatId = $message->getChat()->getId();
        
        $text = "📄 *Imbas Slip Gaji*\n\n";
        $text .= "Sila hantar slip gaji anda dalam format:\n\n";
        $text .= "✅ *PDF* - Format terbaik untuk ketepatan tinggi\n";
        $text .= "✅ *Gambar* - JPG, PNG, JPEG\n\n";
        $text .= "📋 *Tips untuk hasil terbaik:*\n";
        $text .= "• Pastikan teks jelas dan tidak kabur\n";
        $text .= "• Imbas dalam pencahayaan yang baik\n";
        $text .= "• Pastikan semua maklumat kelihatan\n\n";
        $text .= "📤 Hantar fail anda sekarang...";

        try {
            $this->bot->sendMessage($chatId, $text, 'Markdown');
            Log::info('Sent scan instruction to chat: ' . $chatId);
        } catch (\Exception $e) {
            Log::error('Error sending scan instruction: ' . $e->getMessage());
        }
    }

    /**
     * Handle /koperasi command
     */
    public function handleKoperasiCommand(Message $message): void
    {
        $chatId = $message->getChat()->getId();

        try {
            $koperasiList = Koperasi::where('is_active', true)
                ->orderBy('name')
                ->get();

            if ($koperasiList->isEmpty()) {
                $this->bot->sendMessage($chatId, "❌ Tiada koperasi aktif pada masa ini.");
                return;
            }

            $text = "🏦 *Senarai Koperasi Aktif*\n\n";
            
            foreach ($koperasiList as $index => $koperasi) {
                $maxPercentage = $koperasi->rules['max_peratus_gaji_bersih'] ?? 'N/A';
                $minSalary = $koperasi->rules['min_gaji_pokok'] ?? 'N/A';
                
                $text .= "🏢 *{$koperasi->name}*\n";
                $text .= "📊 Max Peratusan: {$maxPercentage}%\n";
                $text .= "💰 Min Gaji Pokok: RM {$minSalary}\n";
                $text .= "📝 Syarat: " . ($koperasi->description ?? 'Tiada maklumat tambahan') . "\n\n";
            }

            $text .= "💡 *Tip:* Hantar slip gaji untuk semakan kelayakan automatik!";

            $this->bot->sendMessage($chatId, $text, 'Markdown');
            Log::info('Sent koperasi list to chat: ' . $chatId);

        } catch (\Exception $e) {
            Log::error('Error fetching koperasi list: ' . $e->getMessage());
            $this->bot->sendMessage($chatId, "❌ Ralat mendapatkan senarai koperasi. Sila cuba lagi.");
        }
    }

    /**
     * Handle /status command
     */
    public function handleStatusCommand(Message $message): void
    {
        $chatId = $message->getChat()->getId();
        $user = $message->getFrom();
        
        try {
            // Get user's recent payslips
            $telegramUser = $this->getTelegramUser($user->getId());
            if (!$telegramUser) {
                $this->bot->sendMessage($chatId, "❌ Pengguna tidak dijumpai. Sila gunakan /start terlebih dahulu.");
                return;
            }

            $recentPayslips = Payslip::where('user_id', $telegramUser->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            if ($recentPayslips->isEmpty()) {
                $text = "📊 *Status Pemprosesan*\n\n";
                $text .= "Tiada slip gaji yang diproses lagi.\n";
                $text .= "Gunakan /scan untuk mula mengimbas slip gaji! 📄";
                $this->bot->sendMessage($chatId, $text, 'Markdown');
            } else {
                $text = "📊 *Status Pemprosesan Terkini*\n\n";
                $inlineKeyboard = [];
                
                foreach ($recentPayslips as $payslip) {
                    $statusIcon = $this->getStatusIcon($payslip->status);
                    $text .= "{$statusIcon} *ID: {$payslip->id}*\n";
                    $text .= "📅 Tarikh: " . $payslip->created_at->format('d/m/Y H:i') . "\n";
                    $text .= "📋 Status: " . ucfirst($payslip->status) . "\n";
                    
                    if ($payslip->status === 'completed' && $payslip->extracted_data) {
                        $data = $payslip->extracted_data;
                        $text .= "💰 Gaji Bersih: RM " . ($data['gaji_bersih'] ?? 'N/A') . "\n";
                        
                        // Add button for completed payslips
                        $inlineKeyboard[] = [
                            [
                                'text' => "📋 Lihat Kelayakan ID: {$payslip->id}",
                                'callback_data' => "view_eligibility_{$payslip->id}"
                            ]
                        ];
                    }
                    $text .= "\n";
                }
                
                if (!empty($inlineKeyboard)) {
                    $text .= "👆 Klik butang di atas untuk melihat kelayakan koperasi bagi slip gaji yang telah selesai diproses.";
                }
                
                $replyMarkup = null;
                if (!empty($inlineKeyboard)) {
                    // Create InlineKeyboardMarkup with the button array
                    $replyMarkup = new InlineKeyboardMarkup();
                    $replyMarkup->setInlineKeyboard($inlineKeyboard);
                }
                
                $this->bot->sendMessage($chatId, $text, 'Markdown', false, null, $replyMarkup);
            }

            Log::info('Sent status to chat: ' . $chatId);

        } catch (\Exception $e) {
            Log::error('Error getting status: ' . $e->getMessage());
            $this->bot->sendMessage($chatId, "❌ Ralat mendapatkan status. Sila cuba lagi.");
        }
    }

    /**
     * Handle callback queries from inline keyboards
     */
    public function handleCallbackQuery($callbackQuery): void
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        $user = $callbackQuery->getFrom();
        
        try {
            // Answer the callback query to remove loading state
            $this->bot->answerCallbackQuery($callbackQuery->getId());
            
            // Handle view eligibility callback
            if (str_starts_with($data, 'view_eligibility_')) {
                $payslipId = (int) str_replace('view_eligibility_', '', $data);
                $this->showEligibilityDetails($chatId, $payslipId, $user);
            }
            
        } catch (\Exception $e) {
            Log::error('Error handling callback query: ' . $e->getMessage());
            $this->bot->sendMessage($chatId, "❌ Ralat memproses permintaan. Sila cuba lagi.");
        }
    }
    
    /**
     * Show detailed eligibility results for a specific payslip
     */
    private function showEligibilityDetails(int $chatId, int $payslipId, $telegramUser): void
    {
        try {
            // Get user and verify ownership
            $user = $this->registerUserIfNotExists($telegramUser);
            
            $payslip = Payslip::where('id', $payslipId)
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->first();
                
            if (!$payslip) {
                $this->bot->sendMessage($chatId, "❌ Slip gaji tidak dijumpai atau belum selesai diproses.");
                return;
            }
            
            if (!$payslip->extracted_data || !isset($payslip->extracted_data['koperasi_results'])) {
                $this->bot->sendMessage($chatId, "❌ Tiada data kelayakan untuk slip gaji ini.");
                return;
            }
            
            $extractedData = $payslip->extracted_data;
            
            // Use detailed results if available, otherwise fallback to simple results
            if (isset($payslip->extracted_data['detailed_koperasi_results'])) {
                $detailedKoperasiResults = $payslip->extracted_data['detailed_koperasi_results'];
            } else {
                // Fallback for older payslips without detailed results
                $koperasiResults = $payslip->extracted_data['koperasi_results'];
                $detailedKoperasiResults = [];
                foreach ($koperasiResults as $koperasiName => $isEligible) {
                    $detailedKoperasiResults[$koperasiName] = [
                        'eligible' => $isEligible,
                        'reasons' => [$isEligible ? 'Layak' : 'Tidak layak berdasarkan peratus gaji bersih']
                    ];
                }
            }
            
            // Build detailed message
            $text = "🎉 *Analisis Slip Gaji Selesai!*\n\n";
            $text .= "🆔 ID: {$payslip->id}\n";
            $text .= "📄 Fail: " . $this->escapeMarkdown($payslip->original_filename) . "\n\n";
            
            $text .= "💰 *Maklumat Gaji:*\n";
            $text .= "• Gaji Pokok: RM " . number_format($extractedData['gaji_pokok'] ?? 0, 2) . "\n";
            $text .= "• Gaji Bersih: RM " . number_format($extractedData['gaji_bersih'] ?? 0, 2) . "\n\n";
            
            $text .= "🏦 *Kelayakan Koperasi:*\n";
            
            foreach ($detailedKoperasiResults as $koperasiName => $result) {
                $status = $result['eligible'] ? '✅' : '❌';
                $text .= "{$status} *" . $this->escapeMarkdown($koperasiName) . "*\n";
                
                // Show all reasons
                foreach ($result['reasons'] as $reason) {
                    $text .= "└ " . $this->escapeMarkdown($reason) . "\n";
                }
                $text .= "\n";
            }
            
            $text .= "📊 Gunakan /status untuk melihat semua analisis anda.";
            
            $this->bot->sendMessage($chatId, $text, 'Markdown');
            Log::info("Sent eligibility details for payslip {$payslipId} to chat {$chatId}");
            
        } catch (\Exception $e) {
            Log::error("Error showing eligibility details: " . $e->getMessage());
            $this->bot->sendMessage($chatId, "❌ Ralat menunjukkan butiran kelayakan. Sila cuba lagi.");
        }
    }

    /**
     * Handle text messages (menu buttons)
     */
    public function handleTextMessage(Message $message): void
    {
        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        // Handle menu buttons
        switch ($text) {
            case '📄 Imbas Slip Gaji':
                $this->handleScanCommand($message);
                break;
            case '🏦 Senarai Koperasi':
                $this->handleKoperasiCommand($message);
                break;
            case '📊 Semak Status':
                $this->handleStatusCommand($message);
                break;
            case '❓ Bantuan':
                $this->handleHelpCommand($message);
                break;
            default:
                $response = "🤔 Saya tidak faham arahan tersebut.\n\n";
                $response .= "Gunakan butang menu di bawah atau arahan berikut:\n";
                $response .= "/scan - Imbas slip gaji\n";
                $response .= "/help - Bantuan lengkap";
                
                try {
                    $this->bot->sendMessage($chatId, $response);
                    Log::info('Sent unknown command response to chat: ' . $chatId);
                } catch (\Exception $e) {
                    Log::error('Error sending unknown command response: ' . $e->getMessage());
                }
        }
    }

    /**
     * Handle document uploads
     */
    public function handleDocumentUpload(Message $message): void
    {
        $chatId = $message->getChat()->getId();
        $document = $message->getDocument();
        $user = $message->getFrom();

        try {
            // Send processing message
            $this->bot->sendMessage($chatId, "📄 Dokumen diterima! Sedang memproses...");

            // Validate file type
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($document->getMimeType(), $allowedTypes)) {
                $this->bot->sendMessage($chatId, "❌ Format fail tidak disokong. Sila hantar PDF atau gambar (JPG/PNG).");
                return;
            }

            // Download and process file
            $this->processUploadedFile($chatId, $document->getFileId(), $document->getFileName(), $user);

        } catch (\Exception $e) {
            Log::error('Error handling document upload: ' . $e->getMessage());
            $this->bot->sendMessage($chatId, "❌ Ralat memproses dokumen. Sila cuba lagi.");
        }
    }

    /**
     * Handle photo uploads
     */
    public function handlePhotoUpload(Message $message): void
    {
        $chatId = $message->getChat()->getId();
        $photos = $message->getPhoto();
        $user = $message->getFrom();

        try {
            // Send processing message
            $this->bot->sendMessage($chatId, "📷 Gambar diterima! Sedang memproses...");

            // Get the largest photo
            $largestPhoto = end($photos);
            
            // Download and process file
            $this->processUploadedFile($chatId, $largestPhoto->getFileId(), 'payslip_photo.jpg', $user);

        } catch (\Exception $e) {
            Log::error('Error handling photo upload: ' . $e->getMessage());
            $this->bot->sendMessage($chatId, "❌ Ralat memproses gambar. Sila cuba lagi.");
        }
    }

    /**
     * Process uploaded file
     */
    private function processUploadedFile(int $chatId, string $fileId, string $fileName, $telegramUser): void
    {
        try {
            // Get file info from Telegram
            $file = $this->bot->getFile($fileId);
            $filePath = $file->getFilePath();
            
            // Download file content
            $fileContent = $this->bot->downloadFile($fileId);
            
            // Save file to storage
            $storagePath = 'telegram_uploads/' . date('Y/m/d/') . $fileName;
            Storage::disk('local')->put($storagePath, $fileContent);
            
            // Get or create user
            $user = $this->registerUserIfNotExists($telegramUser);
            
            // Create payslip record
            $payslip = Payslip::create([
                'user_id' => $user->id,
                'file_path' => $storagePath,
                'original_filename' => $fileName,
                'status' => 'pending',
                'source' => 'telegram',
                'telegram_chat_id' => $chatId,
            ]);

            // Send confirmation
            $text = "✅ *Slip gaji berjaya dimuat naik!*\n\n";
            $text .= "🆔 ID Pemprosesan: {$payslip->id}\n";
            $text .= "📄 Nama Fail: " . $this->escapeMarkdown($fileName) . "\n";
            $text .= "⏳ Status: Sedang diproses\n\n";
            $text .= "Saya akan menghantar hasil analisis sebaik sahaja selesai. Biasanya mengambil masa 1-2 minit. ⏱️\n\n";
            $text .= "Gunakan /status untuk semak kemajuan.";

            $this->bot->sendMessage($chatId, $text, 'Markdown');
            
            Log::info("Created payslip {$payslip->id} for Telegram user {$telegramUser->getId()}");

        } catch (\Exception $e) {
            Log::error('Error processing uploaded file: ' . $e->getMessage());
            $this->bot->sendMessage($chatId, "❌ Ralat memproses fail. Sila cuba lagi atau hubungi sokongan.");
            return;
        }

        // Dispatch processing job separately to avoid showing errors for job dispatch issues
        try {
            ProcessPayslip::dispatch($payslip);
        } catch (\Exception $e) {
            Log::warning("Job dispatch warning for payslip {$payslip->id}: " . $e->getMessage());
            // Don't show error to user as the file was uploaded successfully
        }
    }

    /**
     * Register Telegram user if not exists
     */
    private function registerUserIfNotExists($telegramUser): User
    {
        $email = $telegramUser->getId() . '@telegram.bot';
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $user = User::create([
                'name' => $telegramUser->getFirstName() . ' ' . ($telegramUser->getLastName() ?? ''),
                'email' => $email,
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                'role_id' => 1, // Default role
                'is_active' => true,
                'telegram_user_id' => $telegramUser->getId(),
                'telegram_username' => $telegramUser->getUsername(),
            ]);
            
            Log::info("Registered new Telegram user: {$user->email}");
        }
        
        return $user;
    }

    /**
     * Get user by Telegram ID
     */
    private function getTelegramUser(int $telegramId): ?User
    {
        return User::where('telegram_user_id', $telegramId)->first();
    }

    /**
     * Get status icon for payslip status
     */
    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => '⏳',
            'processing' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            default => '❓'
        };
    }

    /**
     * Send processing result to user
     */
    public function sendProcessingResult(Payslip $payslip, array $eligibilityResults = []): void
    {
        if (!$payslip->telegram_chat_id) {
            return;
        }

        try {
            $chatId = $payslip->telegram_chat_id;
            
            if ($payslip->status === 'completed' && $payslip->extracted_data) {
                $data = $payslip->extracted_data;
                
                $text = "🎉 *Analisis Slip Gaji Selesai!*\n\n";
                $text .= "🆔 ID: " . $payslip->id . "\n";
                $text .= "📄 Fail: " . $this->escapeMarkdown($payslip->original_filename) . "\n\n";
                $text .= "💰 *Maklumat Gaji:*\n";
                $text .= "• Gaji Pokok: RM " . number_format($data['gaji_pokok'] ?? 0, 2) . "\n";
                $text .= "• Gaji Bersih: RM " . number_format($data['gaji_bersih'] ?? 0, 2) . "\n";
                
                if (!empty($eligibilityResults)) {
                    $text .= "\n🏦 *Kelayakan Koperasi:*\n";
                    foreach ($eligibilityResults as $result) {
                        $icon = $result['eligible'] ? '✅' : '❌';
                        $text .= "{$icon} " . $this->escapeMarkdown($result['koperasi_name']) . "\n";
                        
                        // Handle both old format (single reason) and new format (multiple reasons)
                        if (isset($result['reasons']) && is_array($result['reasons'])) {
                            foreach ($result['reasons'] as $reason) {
                                $text .= "   └ " . $this->escapeMarkdown($reason) . "\n";
                            }
                        } elseif (isset($result['reason'])) {
                            $text .= "   └ " . $this->escapeMarkdown($result['reason']) . "\n";
                        }
                    }
                }
                
                $text .= "\n📊 Gunakan /status untuk melihat semua analisis anda.";
                
            } else {
                $text = "❌ *Analisis Gagal*\n\n";
                $text .= "🆔 ID: " . $payslip->id . "\n";
                $text .= "📄 Fail: " . $this->escapeMarkdown($payslip->original_filename) . "\n\n";
                $text .= "Maaf, kami tidak dapat memproses slip gaji anda. Sila pastikan:\n";
                $text .= "• Gambar/PDF jelas dan tidak kabur\n";
                $text .= "• Semua teks kelihatan dengan jelas\n";
                $text .= "• Format fail disokong (PDF/JPG/PNG)\n\n";
                $text .= "Sila cuba lagi dengan fail yang lebih jelas. 📄";
            }

            $this->bot->sendMessage($chatId, $text, 'Markdown');
            Log::info("Sent processing result to chat {$chatId} for payslip {$payslip->id}");

        } catch (\Exception $e) {
            Log::error("Error sending processing result: " . $e->getMessage());
        }
    }

    /**
     * Escape special characters for Telegram Markdown
     */
    private function escapeMarkdown(string $text): string
    {
        // Escape special Markdown characters for Telegram
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        
        return $text;
    }
} 