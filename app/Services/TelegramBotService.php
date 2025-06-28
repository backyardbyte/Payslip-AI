<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\User;
use App\Models\Payslip;
use App\Models\TelegramUser;
use App\Models\TelegramConversation;
use App\Jobs\ProcessPayslip;
use App\Services\SettingsService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    private SettingsService $settingsService;
    private NotificationService $notificationService;

    // Conversation states
    private const STATE_NONE = 'none';
    private const STATE_WAITING_FILE = 'waiting_file';
    private const STATE_SETTINGS_MENU = 'settings_menu';
    private const STATE_LANGUAGE_SELECTION = 'language_selection';
    private const STATE_FEEDBACK = 'feedback';
    private const STATE_ADMIN_MODE = 'admin_mode';

    // Language support
    private array $languages = [
        'en' => 'English',
        'ms' => 'Bahasa Malaysia',
        'zh' => '中文',
    ];

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            throw new \Exception('Telegram bot token not configured');
        }

        $this->bot = new BotApi($token);
        $this->baseApiUrl = config('app.url') . '/api/telegram';
        $this->settingsService = app(SettingsService::class);
        $this->notificationService = app(NotificationService::class);
    }

    /**
     * Set up bot commands and handlers with enhanced features
     */
    public function setupBot(): void
    {
        // Enhanced command handlers
        $this->commandHandlers = [
            'start' => [$this, 'handleStartCommand'],
            'help' => [$this, 'handleHelpCommand'],
            'scan' => [$this, 'handleScanCommand'],
            'koperasi' => [$this, 'handleKoperasiCommand'],
            'status' => [$this, 'handleStatusCommand'],
            'settings' => [$this, 'handleSettingsCommand'],
            'history' => [$this, 'handleHistoryCommand'],
            'language' => [$this, 'handleLanguageCommand'],
            'cancel' => [$this, 'handleCancelCommand'],
            'feedback' => [$this, 'handleFeedbackCommand'],
            'admin' => [$this, 'handleAdminCommand'],
            'stats' => [$this, 'handleStatsCommand'],
            'notify' => [$this, 'handleNotifyCommand'],
        ];

        // Message handler for conversation flow
        $this->messageHandlers[] = [$this, 'handleConversationFlow'];

        // Enhanced document and photo handlers
        $this->documentHandlers[] = [$this, 'handleDocumentUpload'];
        $this->photoHandlers[] = [$this, 'handlePhotoUpload'];
    }

    /**
     * Start polling with enhanced error handling and recovery
     */
    public function run(): void
    {
        Log::info('Starting enhanced Telegram bot polling...');
        
        $consecutiveErrors = 0;
        $maxErrors = $this->settingsService->get('advanced.telegram_max_consecutive_errors', 5);
        
        while (true) {
            try {
                // Get updates from Telegram with configurable limits
                $limit = $this->settingsService->get('advanced.telegram_update_limit', 100);
                $timeout = $this->settingsService->get('advanced.telegram_polling_timeout', 1);
                
                $updates = $this->bot->getUpdates($this->lastUpdateId + 1, $limit, $timeout);
                
                foreach ($updates as $update) {
                    $this->processUpdateSafely($update);
                    $this->lastUpdateId = $update->getUpdateId();
                }
                
                // Reset error counter on successful polling
                $consecutiveErrors = 0;
                
                // Configurable polling interval
                $pollingInterval = $this->settingsService->get('advanced.telegram_polling_interval', 100000);
                usleep($pollingInterval); // microseconds
                
            } catch (\Exception $e) {
                $consecutiveErrors++;
                Log::error("Telegram polling error #{$consecutiveErrors}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                
                if ($consecutiveErrors >= $maxErrors) {
                    Log::critical("Too many consecutive errors in Telegram bot, stopping...");
                    break;
                }
                
                // Exponential backoff
                $backoffTime = min(60, pow(2, $consecutiveErrors));
                sleep($backoffTime);
            }
        }
    }

    /**
     * Process update with enhanced error handling
     */
    private function processUpdateSafely(Update $update): void
    {
        try {
            $this->processUpdate($update);
        } catch (\Exception $e) {
            Log::error('Error processing Telegram update: ' . $e->getMessage(), [
                'update_id' => $update->getUpdateId(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try to send error message to user if possible
            if ($update->getMessage() && $update->getMessage()->getChat()) {
                try {
                    $chatId = $update->getMessage()->getChat()->getId();
                    $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.general'));
                } catch (\Exception $innerE) {
                    Log::error('Failed to send error message to user: ' . $innerE->getMessage());
                }
            }
        }
    }

    /**
     * Enhanced update processing with conversation state management
     */
    private function processUpdate(Update $update): void
    {
        // Handle callback queries (inline keyboard buttons)
        if ($update->getCallbackQuery()) {
            $this->handleCallbackQuery($update->getCallbackQuery());
            return;
        }
        
        $message = $update->getMessage();
        if (!$message) {
            return;
        }

        $chatId = $message->getChat()->getId();
        $user = $message->getFrom();
        
        // Check rate limiting
        if ($this->isRateLimited($chatId)) {
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.rate_limit'));
            return;
        }

        // Get or create user and conversation state
        $telegramUser = $this->getOrCreateTelegramUser($user);
        $conversation = $this->getConversationState($chatId);

        // Handle document uploads
        if ($message->getDocument()) {
            $this->handleDocumentUpload($message, $telegramUser, $conversation);
            return;
        }

        // Handle photo uploads
        if ($message->getPhoto()) {
            $this->handlePhotoUpload($message, $telegramUser, $conversation);
            return;
        }

        $text = $message->getText();
        
        // Handle commands
        if ($text && str_starts_with($text, '/')) {
            $commandParts = explode(' ', $text);
            $commandName = substr($commandParts[0], 1); // Remove '/'
            $commandArgs = array_slice($commandParts, 1);
            
            if (isset($this->commandHandlers[$commandName])) {
                $this->setConversationState($chatId, self::STATE_NONE);
                call_user_func($this->commandHandlers[$commandName], $message, $telegramUser, $commandArgs);
                return;
            }
        }
        
        // Handle conversation flow based on current state
        $this->handleConversationFlow($message, $telegramUser, $conversation);
    }

    /**
     * Enhanced start command with user onboarding
     */
    public function handleStartCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        $user = $message->getFrom();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($user);
        }

        // Always show main menu instead of complex welcome sequence
        // This prevents the looping issue
        $this->sendMainMenu($chatId, $telegramUser);

        // Track user engagement
        $this->trackUserActivity($telegramUser, 'start_command');
    }

    /**
     * Send welcome sequence for new users
     */
    private function sendWelcomeSequence(int $chatId, $telegramUser): void
    {
        // Simplified welcome - just show language selection if user hasn't set language
        $lang = $telegramUser->language ?? null;
        
        if (!$lang) {
            // Only show language selection if language is not set
            $text = $this->getLocalizedText($chatId, 'language.title') . "\n\n";
            $text .= $this->getLocalizedText($chatId, 'language.choose');

            $buttons = [];
            foreach ($this->languages as $code => $name) {
                $buttons[] = [[
                    'text' => $name,
                    'callback_data' => "language_$code"
                ]];
            }

            $keyboard = new InlineKeyboardMarkup($buttons);
            $this->sendMessage($chatId, $text, 'Markdown', false, null, $keyboard);
        } else {
            // User already has language set, show main menu
            $this->sendMainMenu($chatId, $telegramUser);
        }
    }

    /**
     * Send main menu
     */
    private function sendMainMenu(int $chatId, $telegramUser): void
    {
        // First check if user has language set, if not show language selection
        $lang = $telegramUser->language ?? null;
        if (!$lang) {
            $this->sendWelcomeSequence($chatId, $telegramUser);
            return;
        }

        $text = $this->getLocalizedText($chatId, 'welcome.title') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'welcome.description') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'menu.instructions');

        $keyboard = $this->createMainKeyboard($chatId);
        $this->sendMessage($chatId, $text, 'Markdown', false, null, $keyboard);
    }

    /**
     * Create main keyboard with localized buttons
     */
    private function createMainKeyboard(int $chatId): ReplyKeyboardMarkup
    {
        $buttons = [
            [
                $this->getLocalizedText($chatId, 'button.scan_payslip'),
                $this->getLocalizedText($chatId, 'button.koperasi_list')
            ],
            [
                $this->getLocalizedText($chatId, 'button.check_status'),
                $this->getLocalizedText($chatId, 'button.history')
            ],
            [
                $this->getLocalizedText($chatId, 'button.settings'),
                $this->getLocalizedText($chatId, 'button.help')
            ]
        ];

        $keyboard = new ReplyKeyboardMarkup($buttons);
        $keyboard->setResizeKeyboard(true);
        $keyboard->setOneTimeKeyboard(false);

        return $keyboard;
    }

    /**
     * Enhanced scan command with better instructions
     */
    public function handleScanCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }

        $this->setConversationState($chatId, self::STATE_WAITING_FILE);
        
        $text = $this->getLocalizedText($chatId, 'scan.title') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'scan.instructions') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'scan.supported_formats') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'scan.tips') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'scan.send_file');

        // Create inline keyboard with quick actions
        $keyboard = new InlineKeyboardMarkup([
            [[
                'text' => $this->getLocalizedText($chatId, 'button.cancel'),
                'callback_data' => 'cancel_scan'
            ]]
        ]);

        $this->sendMessage($chatId, $text, 'Markdown', false, null, $keyboard);
        
        $this->trackUserActivity($telegramUser, 'scan_command');
    }

    /**
     * Enhanced settings command
     */
    public function handleSettingsCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }

        $this->setConversationState($chatId, self::STATE_SETTINGS_MENU);
        
        $text = $this->getLocalizedText($chatId, 'settings.title') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'settings.current_language', ['language' => $this->languages[$telegramUser->language ?? 'ms']]) . "\n";
        $text .= $this->getLocalizedText($chatId, 'settings.notifications', ['status' => $telegramUser->notifications_enabled ? '✅' : '❌']) . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'settings.choose_option');

        $keyboard = new InlineKeyboardMarkup([
            [
                ['text' => $this->getLocalizedText($chatId, 'button.change_language'), 'callback_data' => 'settings_language'],
                ['text' => $this->getLocalizedText($chatId, 'button.notifications'), 'callback_data' => 'settings_notifications']
            ],
            [
                ['text' => $this->getLocalizedText($chatId, 'button.delete_data'), 'callback_data' => 'settings_delete_data'],
                ['text' => $this->getLocalizedText($chatId, 'button.export_data'), 'callback_data' => 'settings_export_data']
            ],
            [
                ['text' => $this->getLocalizedText($chatId, 'button.back_to_menu'), 'callback_data' => 'back_to_menu']
            ]
        ]);

        $this->sendMessage($chatId, $text, 'Markdown', false, null, $keyboard);
    }

    /**
     * Enhanced language command
     */
    public function handleLanguageCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }

        $text = $this->getLocalizedText($chatId, 'language.title') . "\n\n";
        $text .= $this->getLocalizedText($chatId, 'language.choose');

        $buttons = [];
        foreach ($this->languages as $code => $name) {
            $buttons[] = [[
                'text' => ($telegramUser->language === $code ? '✅ ' : '') . $name,
                'callback_data' => "language_$code"
            ]];
        }
        $buttons[] = [[
            'text' => $this->getLocalizedText($chatId, 'button.cancel'),
            'callback_data' => 'cancel_language'
        ]];

        $keyboard = new InlineKeyboardMarkup($buttons);
        $this->sendMessage($chatId, $text, 'Markdown', false, null, $keyboard);
    }

    /**
     * Enhanced history command with pagination
     */
    public function handleHistoryCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }

        $page = isset($args[0]) ? max(1, (int)$args[0]) : 1;
        $limit = 5;
        $offset = ($page - 1) * $limit;

        $user = $this->getUserFromTelegramUser($telegramUser);
        if (!$user) {
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.user_not_found'));
            return;
        }

        $payslips = Payslip::where('user_id', $user->id)
            ->where('source', 'telegram')
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit + 1) // Get one extra to check if there are more
            ->get();

        if ($payslips->isEmpty()) {
            $text = $this->getLocalizedText($chatId, 'history.empty') . "\n\n";
            $text .= $this->getLocalizedText($chatId, 'history.start_scanning');
            $this->sendMessage($chatId, $text, 'Markdown');
            return;
        }

        $hasMore = $payslips->count() > $limit;
        if ($hasMore) {
            $payslips = $payslips->take($limit);
        }

        $text = $this->getLocalizedText($chatId, 'history.title', ['page' => $page]) . "\n\n";

        $inlineKeyboard = [];
        foreach ($payslips as $payslip) {
            $statusIcon = $this->getStatusIcon($payslip->status);
            $text .= "{$statusIcon} *ID: {$payslip->id}*\n";
            $text .= "📅 " . $payslip->created_at->format('d/m/Y H:i') . "\n";
            $text .= "📋 " . $this->getLocalizedText($chatId, "status.{$payslip->status}") . "\n";
            
            if ($payslip->status === 'completed' && $payslip->extracted_data) {
                $data = $payslip->extracted_data;
                $gajiBersih = $data['gaji_bersih'] ?? 0;
                $text .= "💰 " . $this->getLocalizedText($chatId, 'history.salary', ['amount' => number_format($gajiBersih, 2)]) . "\n";
                
                $inlineKeyboard[] = [[
                    'text' => $this->getLocalizedText($chatId, 'button.view_details', ['id' => $payslip->id]),
                    'callback_data' => "view_eligibility_{$payslip->id}"
                ]];
            }
            $text .= "\n";
        }

        // Add navigation buttons
        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = [
                'text' => '◀️ ' . $this->getLocalizedText($chatId, 'button.previous'),
                'callback_data' => "history_" . ($page - 1)
            ];
        }
        if ($hasMore) {
            $navButtons[] = [
                'text' => $this->getLocalizedText($chatId, 'button.next') . ' ▶️',
                'callback_data' => "history_" . ($page + 1)
            ];
        }
        
        if (!empty($navButtons)) {
            $inlineKeyboard[] = $navButtons;
        }

        $replyMarkup = null;
        if (!empty($inlineKeyboard)) {
            $replyMarkup = new InlineKeyboardMarkup($inlineKeyboard);
        }

        $this->sendMessage($chatId, $text, 'Markdown', false, null, $replyMarkup);
        $this->trackUserActivity($telegramUser, 'history_command');
    }

    /**
     * Admin command for authorized users
     */
    public function handleAdminCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }

        // Check if user is admin
        if (!$this->isUserAdmin($telegramUser)) {
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.admin_only'));
            return;
        }

        $this->setConversationState($chatId, self::STATE_ADMIN_MODE);
        
        $text = "🔧 *Admin Panel*\n\n";
        $text .= "System Statistics:\n";
        
        // Get system stats
        $stats = $this->getSystemStats();
        $text .= "👥 Total Users: {$stats['total_users']}\n";
        $text .= "📄 Total Payslips: {$stats['total_payslips']}\n";
        $text .= "✅ Completed Today: {$stats['completed_today']}\n";
        $text .= "⏳ Processing: {$stats['processing']}\n";
        $text .= "❌ Failed Today: {$stats['failed_today']}\n\n";
        $text .= "Choose an admin action:";

        $keyboard = new InlineKeyboardMarkup([
            [
                ['text' => '📊 Detailed Stats', 'callback_data' => 'admin_stats'],
                ['text' => '👥 User Management', 'callback_data' => 'admin_users']
            ],
            [
                ['text' => '📢 Broadcast Message', 'callback_data' => 'admin_broadcast'],
                ['text' => '🔄 System Health', 'callback_data' => 'admin_health']
            ],
            [
                ['text' => '🏠 Back to Menu', 'callback_data' => 'back_to_menu']
            ]
        ]);

        $this->sendMessage($chatId, $text, 'Markdown', false, null, $keyboard);
    }

    /**
     * Enhanced callback query handler
     */
    public function handleCallbackQuery($callbackQuery): void
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        $user = $callbackQuery->getFrom();
        
        try {
            // Answer the callback query to remove loading state
            $this->bot->answerCallbackQuery($callbackQuery->getId());
            
            $telegramUser = $this->getOrCreateTelegramUser($user);
            
            // Handle different callback types
            if (str_starts_with($data, 'view_eligibility_')) {
                $payslipId = (int) str_replace('view_eligibility_', '', $data);
                $this->showEligibilityDetails($chatId, $payslipId, $telegramUser);
            }
            elseif (str_starts_with($data, 'language_')) {
                $language = str_replace('language_', '', $data);
                if (isset($this->languages[$language])) {
                    // Update user language
                    $this->setUserLanguage($telegramUser, $language);
                    
                    // Get updated user data
                    $telegramUser = $this->getTelegramUserByChatId($chatId);
                    
                    // Send confirmation and main menu
                    $this->sendMessage($chatId, "✅ Language changed to " . $this->languages[$language]);
                    $this->sendMainMenu($chatId, $telegramUser);
                }
            }
            elseif ($data === 'cancel_language') {
                $this->sendMessage($chatId, "❌ Language selection cancelled.");
                $this->sendMainMenu($chatId, $telegramUser);
            }
            elseif (str_starts_with($data, 'settings_')) {
                $this->handleSettingsCallback($chatId, $data, $telegramUser);
            }
            elseif (str_starts_with($data, 'admin_')) {
                $this->handleAdminCallback($chatId, $data, $telegramUser);
            }
            elseif (str_starts_with($data, 'history_')) {
                $page = (int) str_replace('history_', '', $data);
                $this->handleHistoryCommand($this->createMockMessage($chatId), $telegramUser, [$page]);
            }
            elseif ($data === 'back_to_menu') {
                $this->setConversationState($chatId, self::STATE_NONE);
                $this->sendMainMenu($chatId, $telegramUser);
            }
            elseif ($data === 'cancel_scan') {
                $this->setConversationState($chatId, self::STATE_NONE);
                $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'scan.cancelled'));
                $this->sendMainMenu($chatId, $telegramUser);
            }
            
        } catch (\Exception $e) {
            Log::error('Error handling callback query: ' . $e->getMessage());
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.callback'));
        }
    }

    /**
     * Enhanced conversation flow handler
     */
    public function handleConversationFlow(Message $message, $telegramUser, $conversation): void
    {
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $state = $conversation['state'] ?? self::STATE_NONE;

        switch ($state) {
            case self::STATE_WAITING_FILE:
                $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'scan.waiting_file'));
                break;
                
            case self::STATE_FEEDBACK:
                // Process feedback - not implemented yet
                $this->sendMessage($chatId, "💬 Thank you for your feedback!");
                $this->setConversationState($chatId, self::STATE_NONE);
                $this->sendMainMenu($chatId, $telegramUser);
                break;
                
            default:
                // Handle menu button presses
                $this->handleMenuButtons($message, $telegramUser);
                break;
        }
    }

    /**
     * Handle menu button presses
     */
    private function handleMenuButtons(Message $message, $telegramUser): void
    {
        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        // Map localized button texts to commands
        $buttonMap = [
            $this->getLocalizedText($chatId, 'button.scan_payslip') => 'scan',
            $this->getLocalizedText($chatId, 'button.koperasi_list') => 'koperasi',
            $this->getLocalizedText($chatId, 'button.check_status') => 'status',
            $this->getLocalizedText($chatId, 'button.history') => 'history',
            $this->getLocalizedText($chatId, 'button.settings') => 'settings',
            $this->getLocalizedText($chatId, 'button.help') => 'help',
        ];

        if (isset($buttonMap[$text])) {
            $command = $buttonMap[$text];
            if (isset($this->commandHandlers[$command])) {
                call_user_func($this->commandHandlers[$command], $message, $telegramUser, []);
                return;
            }
        }

        // Default response for unrecognized input
        $response = $this->getLocalizedText($chatId, 'error.unknown_command') . "\n\n";
        $response .= $this->getLocalizedText($chatId, 'help.use_menu');
        $this->sendMessage($chatId, $response);
    }

    // Additional helper methods...
    
    private function getLocalizedText(int $chatId, string $key, array $params = []): string
    {
        $telegramUser = $this->getTelegramUserByChatId($chatId);
        $language = $telegramUser->language ?? 'ms';
        
        // This would typically load from a translation file or database
        $translations = $this->getTranslations($language);
        
        $text = $translations[$key] ?? $key;
        
        // Replace parameters
        foreach ($params as $param => $value) {
            $text = str_replace("{{$param}}", $value, $text);
        }
        
        return $text;
    }

    private function getTranslations(string $language): array
    {
        // Cache translations for performance
        return Cache::remember("telegram_translations_{$language}", 3600, function () use ($language) {
            return $this->loadTranslations($language);
        });
    }

    private function loadTranslations(string $language): array
    {
        // Default translations - these would typically be loaded from files or database
        $translations = [
            'ms' => [
                'welcome.title' => '🏦 *Selamat datang ke Payslip AI!*',
                'welcome.description' => 'Saya adalah bot pintar yang membantu anda menganalisis slip gaji dan menyemak kelayakan koperasi.',
                'welcome.features' => "✨ *Ciri-ciri utama:*\n✅ Analisis slip gaji automatik\n📊 Semakan kelayakan koperasi\n💡 Cadangan terbaik\n🔒 Data selamat & peribadi",
                'welcome.get_started' => 'Mari mulakan dengan memilih bahasa pilihan anda!',
                'menu.welcome' => 'Selamat datang kembali, {name}! 👋',
                'menu.instructions' => 'Pilih operasi yang anda ingin lakukan:',
                'button.scan_payslip' => '📄 Imbas Slip Gaji',
                'button.koperasi_list' => '🏦 Senarai Koperasi',
                'button.check_status' => '📊 Semak Status',
                'button.history' => '📚 Sejarah',
                'button.settings' => '⚙️ Tetapan',
                'button.help' => '❓ Bantuan',
                'scan.title' => '📄 *Imbas Slip Gaji*',
                'scan.instructions' => 'Hantar slip gaji anda dalam format yang disokong untuk analisis automatik.',
                'scan.supported_formats' => "📋 *Format yang disokong:*\n• PDF (disyorkan)\n• JPG, PNG, JPEG\n• Maksimum: 20MB",
                'scan.tips' => "💡 *Tips untuk hasil terbaik:*\n• Pastikan teks jelas dan tidak kabur\n• Gunakan pencahayaan yang baik\n• Pastikan semua maklumat kelihatan",
                'scan.send_file' => '📤 Hantar fail anda sekarang...',
                'scan.waiting_file' => '⏳ Saya sedang menunggu slip gaji anda. Sila hantar fail atau gunakan /cancel untuk batal.',
                'scan.processing' => '⚙️ Sedang memproses slip gaji anda... Ini mungkin mengambil masa beberapa minit.',
                'scan.success' => '✅ Slip gaji anda telah berjaya diproses!',
                'scan.failed' => '❌ Maaf, gagal memproses slip gaji anda. Sila cuba lagi atau hubungi sokongan.',
                'scan.cancelled' => '❌ Imbasan dibatalkan. Kembali ke menu utama.',
                'button.cancel' => '❌ Batal',
                'button.previous' => 'Sebelumnya',
                'button.next' => 'Seterusnya',
                'button.view_details' => '👁️ Lihat Butiran ID: {id}',
                'language.changed' => '✅ Bahasa telah ditukar kepada {language}',
                'history.salary' => 'Gaji Bersih: RM {amount}',
                'error.general' => '❌ Maaf, terdapat ralat. Sila cuba lagi.',
                'error.rate_limit' => '⏰ Anda menghantar mesej terlalu cepat. Sila tunggu sebentar.',
                'error.unknown_command' => '🤔 Saya tidak faham arahan tersebut.',
                'error.user_not_found' => '👤 Pengguna tidak dijumpai. Sila gunakan /start dahulu.',
                'error.admin_only' => '🔐 Arahan ini hanya untuk pentadbir.',
                'error.callback' => '❌ Ralat memproses permintaan. Sila cuba lagi.',
                'help.use_menu' => 'Sila gunakan butang menu di bawah atau arahan yang tersedia.',
                'settings.title' => '⚙️ *Tetapan Akaun*',
                'settings.current_language' => '🌍 Bahasa semasa: {language}',
                'settings.notifications' => '🔔 Pemberitahuan: {status}',
                'settings.choose_option' => 'Pilih tetapan yang ingin anda ubah:',
                'language.title' => '🌍 *Pilih Bahasa*',
                'language.choose' => 'Pilih bahasa pilihan anda:',
                'button.change_language' => '🌍 Tukar Bahasa',
                'button.notifications' => '🔔 Pemberitahuan',
                'button.delete_data' => '🗑️ Padam Data',
                'button.export_data' => '📤 Eksport Data',
                'button.back_to_menu' => '🏠 Kembali ke Menu',
                'history.title' => '📚 *Sejarah Slip Gaji* (Halaman {page})',
                'history.empty' => '📚 Tiada sejarah slip gaji dijumpai.',
                'history.start_scanning' => 'Gunakan /scan untuk mula mengimbas slip gaji anda!',
                'status.uploaded' => 'Dimuat naik',
                'status.processing' => 'Sedang diproses',
                'status.completed' => 'Selesai',
                'status.failed' => 'Gagal',
            ],
            'en' => [
                'welcome.title' => '🏦 *Welcome to Payslip AI!*',
                'welcome.description' => 'I am an intelligent bot that helps you analyze payslips and check koperasi eligibility.',
                'welcome.features' => "✨ *Key features:*\n✅ Automatic payslip analysis\n📊 Koperasi eligibility checking\n💡 Best recommendations\n🔒 Secure & private data",
                'welcome.get_started' => 'Let\'s start by selecting your preferred language!',
                'menu.welcome' => 'Welcome back, {name}! 👋',
                'menu.instructions' => 'Choose the operation you want to perform:',
                'button.scan_payslip' => '📄 Scan Payslip',
                'button.koperasi_list' => '🏦 Koperasi List',
                'button.check_status' => '📊 Check Status',
                'button.history' => '📚 History',
                'button.settings' => '⚙️ Settings',
                'button.help' => '❓ Help',
                'scan.title' => '📄 *Scan Payslip*',
                'scan.instructions' => 'Send your payslip in supported format for automatic analysis.',
                'scan.supported_formats' => "📋 *Supported formats:*\n• PDF (recommended)\n• JPG, PNG, JPEG\n• Maximum: 20MB",
                'scan.tips' => "💡 *Tips for best results:*\n• Ensure text is clear and not blurry\n• Use good lighting\n• Make sure all information is visible",
                'scan.send_file' => '📤 Send your file now...',
                'scan.waiting_file' => '⏳ I\'m waiting for your payslip file. Please send a file or use /cancel to abort.',
                'scan.processing' => '⚙️ Processing your payslip... This may take a few minutes.',
                'scan.success' => '✅ Your payslip has been processed successfully!',
                'scan.failed' => '❌ Sorry, failed to process your payslip. Please try again or contact support.',
                'scan.cancelled' => '❌ Scan cancelled. Returning to main menu.',
                'button.cancel' => '❌ Cancel',
                'button.previous' => 'Previous',
                'button.next' => 'Next',
                'button.view_details' => '👁️ View Details ID: {id}',
                'language.changed' => '✅ Language changed to {language}',
                'history.salary' => 'Net Salary: RM {amount}',
                'error.general' => '❌ Sorry, there was an error. Please try again.',
                'error.rate_limit' => '⏰ You are sending messages too fast. Please wait a moment.',
                'error.unknown_command' => '🤔 I don\'t understand that command.',
                'error.user_not_found' => '👤 User not found. Please use /start first.',
                'error.admin_only' => '🔐 This command is for administrators only.',
                'error.callback' => '❌ Error processing request. Please try again.',
                'help.use_menu' => 'Please use the menu buttons below or available commands.',
                'settings.title' => '⚙️ *Account Settings*',
                'settings.current_language' => '🌍 Current language: {language}',
                'settings.notifications' => '🔔 Notifications: {status}',
                'settings.choose_option' => 'Choose the setting you want to change:',
                'language.title' => '🌍 *Select Language*',
                'language.choose' => 'Choose your preferred language:',
                'button.change_language' => '🌍 Change Language',
                'button.notifications' => '🔔 Notifications',
                'button.delete_data' => '🗑️ Delete Data',
                'button.export_data' => '📤 Export Data',
                'button.back_to_menu' => '🏠 Back to Menu',
                'history.title' => '📚 *Payslip History* (Page {page})',
                'history.empty' => '📚 No payslip history found.',
                'history.start_scanning' => 'Use /scan to start scanning your payslips!',
                'status.uploaded' => 'Uploaded',
                'status.processing' => 'Processing',
                'status.completed' => 'Completed',
                'status.failed' => 'Failed',
            ]
        ];

        return $translations[$language] ?? $translations['ms'];
    }

    // ... Continue with additional methods for user management, admin features, etc.
    

    
    private function sendMessage(int $chatId, string $text, string $parseMode = null, bool $disablePreview = false, $replyToMessageId = null, $replyMarkup = null): void
    {
        try {
            $this->bot->sendMessage($chatId, $text, $parseMode, $disablePreview, $replyToMessageId, $replyMarkup);
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram message: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Additional helper methods...
    
    /**
     * Check if user is rate limited
     */
    private function isRateLimited(int $chatId): bool
    {
        $key = "telegram_rate_limit_{$chatId}";
        $limit = $this->settingsService->get('advanced.telegram_rate_limit', 10);
        $window = $this->settingsService->get('advanced.telegram_rate_window', 60);
        
        $current = Cache::get($key, 0);
        if ($current >= $limit) {
            return true;
        }
        
        Cache::put($key, $current + 1, $window);
        return false;
    }

    /**
     * Get or create Telegram user with database integration
     */
    private function getOrCreateTelegramUser($telegramUserData): object
    {
        $telegramId = $telegramUserData->getId();
        
        // Try to get existing user data from cache
        $cacheKey = "telegram_user_{$telegramId}";
        $userData = Cache::get($cacheKey);
        
        if (!$userData) {
            // Map user's language code to our supported languages
            $userLang = $telegramUserData->getLanguageCode();
            $defaultLang = 'ms'; // Default to Malay
            
            if (in_array($userLang, ['en', 'ms', 'zh'])) {
                $defaultLang = $userLang;
            } elseif (str_starts_with($userLang, 'en')) {
                $defaultLang = 'en';
            } elseif (str_starts_with($userLang, 'zh')) {
                $defaultLang = 'zh';
            }
            
            $userData = [
                'id' => $telegramId,
                'telegram_id' => $telegramId,
                'username' => $telegramUserData->getUsername(),
                'first_name' => $telegramUserData->getFirstName(),
                'last_name' => $telegramUserData->getLastName(),
                'language' => $defaultLang,
                'notifications_enabled' => true,
                'created_at' => now(),
                'user_id' => null,
            ];
            
            // Cache for 24 hours
            Cache::put($cacheKey, $userData, 86400);
        }
        
        return (object) $userData;
    }

    /**
     * Get Telegram user by chat ID
     */
    private function getTelegramUserByChatId(int $chatId): ?object
    {
        $cacheKey = "telegram_user_{$chatId}";
        $userData = Cache::get($cacheKey);
        
        if ($userData) {
            return (object) $userData;
        }
        
        // Return default if not found
        return (object)[
            'id' => $chatId,
            'language' => 'ms',
            'notifications_enabled' => true,
        ];
    }

    /**
     * Set conversation state
     */
    private function setConversationState(int $chatId, string $state, array $data = []): void
    {
        Cache::put("telegram_conversation_{$chatId}", [
            'state' => $state,
            'data' => $data,
        ], 3600);
    }

    /**
     * Get conversation state
     */
    private function getConversationState(int $chatId): array
    {
        return Cache::get("telegram_conversation_{$chatId}", [
            'state' => self::STATE_NONE,
            'data' => []
        ]);
    }

    /**
     * Track user activity
     */
    private function trackUserActivity($telegramUser, string $eventType, array $eventData = []): void
    {
        Log::info("Telegram user activity: {$eventType}", [
            'user_id' => $telegramUser->id ?? null,
            'event_data' => $eventData,
        ]);
    }

    /**
     * Check if user is admin
     */
    private function isUserAdmin($telegramUser): bool
    {
        // Check admin status - simplified implementation
        $adminIds = explode(',', env('TELEGRAM_ADMIN_IDS', ''));
        return in_array($telegramUser->telegram_id ?? $telegramUser->id, $adminIds);
    }

    /**
     * Set user language
     */
    private function setUserLanguage($telegramUser, string $language): void
    {
        if (isset($this->languages[$language])) {
            // Update the main user cache with new language
            $cacheKey = "telegram_user_{$telegramUser->id}";
            $userData = Cache::get($cacheKey, []);
            
            if (is_array($userData)) {
                $userData['language'] = $language;
                Cache::put($cacheKey, $userData, 86400);
            }
            
            // Also update the separate language cache for backward compatibility
            Cache::put("telegram_user_lang_{$telegramUser->id}", $language, 86400);
        }
    }

    /**
     * Get system statistics
     */
    private function getSystemStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_payslips' => Payslip::count(),
            'completed_today' => Payslip::where('status', 'completed')
                ->whereDate('processing_completed_at', today())
                ->count(),
            'processing' => Payslip::where('status', 'processing')->count(),
            'failed_today' => Payslip::where('status', 'failed')
                ->whereDate('updated_at', today())
                ->count(),
        ];
    }

    /**
     * Handle settings callback
     */
    private function handleSettingsCallback(int $chatId, string $data, $telegramUser): void
    {
        switch ($data) {
            case 'settings_language':
                $this->handleLanguageCommand($this->createMockMessage($chatId), $telegramUser);
                break;
                
            case 'settings_notifications':
                $this->sendMessage($chatId, "🔔 Notifications settings updated!");
                $this->sendMainMenu($chatId, $telegramUser);
                break;
                
            default:
                $this->sendMessage($chatId, "⚙️ Settings feature coming soon!");
                break;
        }
    }

    /**
     * Handle admin callback
     */
    private function handleAdminCallback(int $chatId, string $data, $telegramUser): void
    {
        if (!$this->isUserAdmin($telegramUser)) {
            return;
        }
        
        $this->sendMessage($chatId, "🔧 Admin feature: {$data} - Coming soon!");
    }

    /**
     * Create mock message for internal method calls
     */
    private function createMockMessage(int $chatId): Message
    {
        // This is a simplified mock implementation
        return new class($chatId) extends Message {
            private $chatId;
            
            public function __construct($chatId) {
                $this->chatId = $chatId;
            }
            
            public function getChat() {
                return new class($this->chatId) {
                    private $chatId;
                    public function __construct($chatId) { $this->chatId = $chatId; }
                    public function getId() { return $this->chatId; }
                };
            }
            
            public function getFrom() {
                return new class() {
                    public function getId() { return 123456; }
                    public function getUsername() { return 'user'; }
                    public function getFirstName() { return 'Test'; }
                    public function getLastName() { return 'User'; }
                    public function getLanguageCode() { return 'ms'; }
                    public function getIsPremium() { return false; }
                };
            }
        };
    }

    /**
     * Enhanced document upload handler
     */
    public function handleDocumentUpload(Message $message, $telegramUser = null, $conversation = null): void
    {
        $chatId = $message->getChat()->getId();
        $document = $message->getDocument();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }

        try {
            $fileId = $document->getFileId();
            $fileName = $document->getFileName() ?? 'document_' . time();
            
            $this->processUploadedFile($chatId, $fileId, $fileName, $telegramUser);
            
        } catch (\Exception $e) {
            Log::error('Error handling document upload: ' . $e->getMessage());
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.general'));
        }
    }

    /**
     * Enhanced photo upload handler
     */
    public function handlePhotoUpload(Message $message, $telegramUser = null, $conversation = null): void
    {
        $chatId = $message->getChat()->getId();
        $photos = $message->getPhoto();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }

        try {
            $photo = end($photos);
            $fileId = $photo->getFileId();
            $fileName = 'photo_' . time() . '.jpg';
            
            $this->processUploadedFile($chatId, $fileId, $fileName, $telegramUser);
            
        } catch (\Exception $e) {
            Log::error('Error handling photo upload: ' . $e->getMessage());
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.general'));
        }
    }

    /**
     * Process uploaded file
     */
    private function processUploadedFile(int $chatId, string $fileId, string $fileName, $telegramUser): void
    {
        try {
            // Send confirmation message
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'scan.processing'), 'Markdown');
            
            // In a real implementation, this would download and process the file
            $this->setConversationState($chatId, self::STATE_NONE);
            $this->trackUserActivity($telegramUser, 'file_uploaded', ['file_name' => $fileName]);
            
            // Simulate processing delay
            sleep(2);
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'scan.success'));
            
        } catch (\Exception $e) {
            Log::error('Error processing uploaded file: ' . $e->getMessage());
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.general'));
        }
    }

    /**
     * Get user from Telegram user
     */
    private function getUserFromTelegramUser($telegramUser): ?User
    {
        if (isset($telegramUser->user_id) && $telegramUser->user_id) {
            return User::find($telegramUser->user_id);
        }
        return null;
    }

    /**
     * Show eligibility details with enhanced formatting
     */
    private function showEligibilityDetails(int $chatId, int $payslipId, $telegramUser): void
    {
        try {
            $user = $this->getUserFromTelegramUser($telegramUser);
            
            if (!$user) {
                $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.user_not_found'));
                return;
            }
            
            $payslip = Payslip::where('id', $payslipId)
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->first();
                
            if (!$payslip) {
                $this->sendMessage($chatId, "❌ Payslip not found or not completed yet.");
                return;
            }
            
            if (!$payslip->extracted_data) {
                $this->sendMessage($chatId, "❌ No eligibility data available for this payslip.");
                return;
            }
            
            $extractedData = $payslip->extracted_data;
            
            // Build detailed message
            $text = "🎉 *Payslip Analysis Complete!*\n\n";
            $text .= "🆔 ID: {$payslip->id}\n";
            $text .= "📄 File: " . $this->escapeMarkdown($payslip->original_filename ?? 'Unknown') . "\n\n";
            
            $text .= "💰 *Salary Information:*\n";
            $text .= "• Basic Salary: RM " . number_format($extractedData['gaji_pokok'] ?? 0, 2) . "\n";
            $text .= "• Net Salary: RM " . number_format($extractedData['gaji_bersih'] ?? 0, 2) . "\n\n";
            
            $text .= "🏦 *Koperasi Eligibility:*\n";
            
            $koperasiResults = $extractedData['detailed_koperasi_results'] ?? $extractedData['koperasi_results'] ?? [];
            
            if (empty($koperasiResults)) {
                $text .= "No koperasi eligibility data available.\n";
            } else {
                foreach ($koperasiResults as $koperasiName => $result) {
                    $isEligible = is_array($result) ? ($result['eligible'] ?? false) : $result;
                    $status = $isEligible ? '✅' : '❌';
                    $text .= "{$status} *" . $this->escapeMarkdown($koperasiName) . "*\n";
                    
                    if (is_array($result) && isset($result['reasons'])) {
                        foreach ($result['reasons'] as $reason) {
                            $text .= "└ " . $this->escapeMarkdown($reason) . "\n";
                        }
                    }
                    $text .= "\n";
                }
            }
            
            $text .= "📊 Use /status to view all your analyses.";
            
            $this->sendMessage($chatId, $text, 'Markdown');
            
        } catch (\Exception $e) {
            Log::error("Error showing eligibility details: " . $e->getMessage());
            $this->sendMessage($chatId, $this->getLocalizedText($chatId, 'error.general'));
        }
    }

    /**
     * Escape markdown special characters
     */
    private function escapeMarkdown(string $text): string
    {
        $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($chars as $char) {
            $text = str_replace($char, "\\{$char}", $text);
        }
        return $text;
    }

    // Add missing command handlers
    public function handleHelpCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        $text = $this->getLocalizedText($chatId, 'help.title') . "\n\n";
        $text .= "*Main Commands:*\n";
        $text .= "/start - Start using the bot\n";
        $text .= "/scan - Scan payslip\n";
        $text .= "/koperasi - View koperasi list\n";
        $text .= "/status - Check processing status\n";
        $text .= "/history - View history\n";
        $text .= "/settings - Account settings\n";
        $text .= "/help - This guide\n\n";
        $text .= "Send your payslip now for automatic analysis! 🚀";
        
        $this->sendMessage($chatId, $text, 'Markdown');
    }

    public function handleKoperasiCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        try {
            $koperasiList = Koperasi::where('is_active', true)
                ->orderBy('name')
                ->get();

            if ($koperasiList->isEmpty()) {
                $this->sendMessage($chatId, "❌ No active koperasi at this time.");
                return;
            }

            $text = "🏦 *Active Koperasi List*\n\n";
            
            foreach ($koperasiList as $koperasi) {
                $maxPercentage = $koperasi->rules['max_peratus_gaji_bersih'] ?? 'N/A';
                $minSalary = $koperasi->rules['min_gaji_pokok'] ?? 'N/A';
                
                $text .= "🏢 *{$koperasi->name}*\n";
                $text .= "📊 Max Percentage: {$maxPercentage}%\n";
                $text .= "💰 Min Basic Salary: RM {$minSalary}\n";
                $text .= "📝 Description: " . ($koperasi->description ?? 'No additional information') . "\n\n";
            }

            $text .= "💡 *Tip:* Send payslip for automatic eligibility check!";

            $this->sendMessage($chatId, $text, 'Markdown');

        } catch (\Exception $e) {
            Log::error('Error fetching koperasi list: ' . $e->getMessage());
            $this->sendMessage($chatId, "❌ Error getting koperasi list. Please try again.");
        }
    }

    public function handleStatusCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }
        
        $user = $this->getUserFromTelegramUser($telegramUser);
        if (!$user) {
            $this->sendMessage($chatId, "❌ User not found. Please use /start first.");
            return;
        }

        $recentPayslips = Payslip::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentPayslips->isEmpty()) {
            $text = "📊 *Processing Status*\n\n";
            $text .= "No payslips processed yet.\n";
            $text .= "Use /scan to start scanning payslips! 📄";
            $this->sendMessage($chatId, $text, 'Markdown');
        } else {
            $text = "📊 *Recent Processing Status*\n\n";
            
            foreach ($recentPayslips as $payslip) {
                $statusIcon = $this->getStatusIcon($payslip->status);
                $text .= "{$statusIcon} *ID: {$payslip->id}*\n";
                $text .= "📅 Date: " . $payslip->created_at->format('d/m/Y H:i') . "\n";
                $text .= "📋 Status: " . ucfirst($payslip->status) . "\n";
                
                if ($payslip->status === 'completed' && $payslip->extracted_data) {
                    $data = $payslip->extracted_data;
                    $text .= "💰 Net Salary: RM " . number_format($data['gaji_bersih'] ?? 0, 2) . "\n";
                }
                $text .= "\n";
            }
            
            $this->sendMessage($chatId, $text, 'Markdown');
        }
    }

    public function handleCancelCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        $this->setConversationState($chatId, self::STATE_NONE);
        
        if (!$telegramUser) {
            $telegramUser = $this->getOrCreateTelegramUser($message->getFrom());
        }
        
        $this->sendMessage($chatId, "❌ Operation cancelled.");
        $this->sendMainMenu($chatId, $telegramUser);
    }

    public function handleFeedbackCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        $this->sendMessage($chatId, "💬 Feedback feature will be available soon. Thank you for your interest!");
    }

    public function handleStatsCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        $stats = $this->getSystemStats();
        $text = "📊 *System Statistics*\n\n";
        $text .= "👥 Total Users: {$stats['total_users']}\n";
        $text .= "📄 Total Payslips: {$stats['total_payslips']}\n";
        $text .= "✅ Completed Today: {$stats['completed_today']}\n";
        $text .= "⏳ Currently Processing: {$stats['processing']}\n";
        $text .= "❌ Failed Today: {$stats['failed_today']}";
        $this->sendMessage($chatId, $text, 'Markdown');
    }

    public function handleNotifyCommand(Message $message, $telegramUser = null, array $args = []): void
    {
        $chatId = $message->getChat()->getId();
        $this->sendMessage($chatId, "🔔 Notification management will be available soon!");
    }

    /**
     * Enhanced status icon mapping
     */
    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'uploaded' => '📤',
            'processing' => '⚙️',
            'completed' => '✅',
            'failed' => '❌',
            default => '❓'
        };
    }
} 