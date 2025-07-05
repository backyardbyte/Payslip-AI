<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TelegramBotService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TelegramBotManagementController extends Controller
{
    protected TelegramBotService $telegramService;
    protected SettingsService $settingsService;

    public function __construct(TelegramBotService $telegramService, SettingsService $settingsService)
    {
        $this->telegramService = $telegramService;
        $this->settingsService = $settingsService;
        $this->middleware('permission:telegram.manage');
    }

    /**
     * Show the telegram bot management page
     */
    public function index()
    {
        $botStatus = $this->getBotStatus();
        $botInfo = $this->getBotInfo();
        $statistics = $this->getBotStatistics();
        $configuration = $this->getBotConfiguration();

        return Inertia::render('admin/telegram/Index', [
            'botStatus' => $botStatus,
            'botInfo' => $botInfo,
            'statistics' => $statistics,
            'configuration' => $configuration,
            'permissions' => [
                'canManageTelegram' => auth()->user()->hasPermission('telegram.manage'),
                'canViewTelegramLogs' => auth()->user()->hasPermission('telegram.view_logs'),
                'canConfigureTelegram' => auth()->user()->hasPermission('telegram.configure'),
            ],
        ]);
    }

    /**
     * Start the telegram bot
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $useSimple = $request->boolean('simple', false);
            
            // Check if bot is already running
            if ($this->isBotRunning()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telegram bot is already running'
                ], 409);
            }

            // Start the bot using the shell script
            $scriptPath = base_path('telegram-bot-manager.sh');
            $result = Process::run("bash {$scriptPath} start");
            
            if ($result->successful()) {
                // Update bot status cache
                Cache::put('telegram_bot_status', 'running', 3600);
                
                Log::info('Telegram bot started by admin', [
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name,
                    'simple_mode' => $useSimple
                ]);

                // Also log to the telegram bot log file
                $logFile = storage_path('logs/telegram-bot.log');
                $logMessage = '[' . date('Y-m-d H:i:s') . '] Bot started by admin: ' . auth()->user()->name . ' (Simple mode: ' . ($useSimple ? 'Yes' : 'No') . ")\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

                return response()->json([
                    'success' => true,
                    'message' => 'Telegram bot started successfully',
                    'output' => $result->output(),
                    'status' => 'running'
                ]);
            } else {
                Log::error('Failed to start telegram bot', [
                    'error' => $result->errorOutput(),
                    'exit_code' => $result->exitCode()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to start telegram bot',
                    'error' => $result->errorOutput()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error starting telegram bot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error starting telegram bot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop the telegram bot
     */
    public function stop(): JsonResponse
    {
        try {
            // Check if bot is running
            if (!$this->isBotRunning()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telegram bot is not running'
                ], 409);
            }

            // Stop the bot using the shell script
            $scriptPath = base_path('telegram-bot-manager.sh');
            $result = Process::run("bash {$scriptPath} stop");
            
            if ($result->successful()) {
                // Update bot status cache
                Cache::put('telegram_bot_status', 'stopped', 3600);
                
                Log::info('Telegram bot stopped by admin', [
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name
                ]);

                // Also log to the telegram bot log file
                $logFile = storage_path('logs/telegram-bot.log');
                $logMessage = '[' . date('Y-m-d H:i:s') . '] Bot stopped by admin: ' . auth()->user()->name . "\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

                return response()->json([
                    'success' => true,
                    'message' => 'Telegram bot stopped successfully',
                    'output' => $result->output(),
                    'status' => 'stopped'
                ]);
            } else {
                Log::error('Failed to stop telegram bot', [
                    'error' => $result->errorOutput(),
                    'exit_code' => $result->exitCode()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to stop telegram bot',
                    'error' => $result->errorOutput()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error stopping telegram bot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error stopping telegram bot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart the telegram bot
     */
    public function restart(Request $request): JsonResponse
    {
        try {
            $useSimple = $request->boolean('simple', false);
            
            // Restart the bot using the shell script
            $scriptPath = base_path('telegram-bot-manager.sh');
            $result = Process::run("bash {$scriptPath} restart");
            
            if ($result->successful()) {
                // Update bot status cache
                Cache::put('telegram_bot_status', 'running', 3600);
                
                Log::info('Telegram bot restarted by admin', [
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name,
                    'simple_mode' => $useSimple
                ]);

                // Also log to the telegram bot log file
                $logFile = storage_path('logs/telegram-bot.log');
                $logMessage = '[' . date('Y-m-d H:i:s') . '] Bot restarted by admin: ' . auth()->user()->name . ' (Simple mode: ' . ($useSimple ? 'Yes' : 'No') . ")\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

                return response()->json([
                    'success' => true,
                    'message' => 'Telegram bot restarted successfully',
                    'output' => $result->output(),
                    'status' => 'running'
                ]);
            } else {
                Log::error('Failed to restart telegram bot', [
                    'error' => $result->errorOutput(),
                    'exit_code' => $result->exitCode()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restart telegram bot',
                    'error' => $result->errorOutput()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error restarting telegram bot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error restarting telegram bot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bot status
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->getBotStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting telegram bot status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting bot status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bot logs
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $lines = $request->get('lines', 50);
            $lines = min($lines, 1000); // Max 1000 lines
            
            $logFile = storage_path('logs/telegram-bot.log');
            $logs = [];
            
            if (file_exists($logFile)) {
                $command = "tail -n {$lines} {$logFile}";
                $result = Process::run($command);
                
                if ($result->successful()) {
                    $logs = explode("\n", trim($result->output()));
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'total_lines' => count($logs),
                    'file_exists' => file_exists($logFile),
                    'file_size' => file_exists($logFile) ? filesize($logFile) : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting telegram bot logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear bot logs
     */
    public function clearLogs(): JsonResponse
    {
        try {
            $logFile = storage_path('logs/telegram-bot.log');
            
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
                
                Log::info('Telegram bot logs cleared by admin', [
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Telegram bot logs cleared successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Log file not found'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error clearing telegram bot logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error clearing logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test bot connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $token = config('services.telegram.bot_token');
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bot token not configured'
                ], 400);
            }

            // Test bot connection using the service
            $botInfo = $this->getBotInfo();
            
            if ($botInfo['is_configured']) {
                // Log successful connection test
                $logFile = storage_path('logs/telegram-bot.log');
                $logMessage = '[' . date('Y-m-d H:i:s') . '] Connection test successful by admin: ' . auth()->user()->name . "\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

                return response()->json([
                    'success' => true,
                    'message' => 'Bot connection successful',
                    'data' => $botInfo
                ]);
            } else {
                // Log failed connection test
                $logFile = storage_path('logs/telegram-bot.log');
                $logMessage = '[' . date('Y-m-d H:i:s') . '] Connection test failed by admin: ' . auth()->user()->name . "\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

                return response()->json([
                    'success' => false,
                    'message' => 'Bot connection failed',
                    'data' => $botInfo
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error testing telegram bot connection', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error testing connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update bot configuration
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'use_simple_bot' => 'boolean',
                'polling_interval' => 'integer|min:100|max:10000',
                'rate_limit' => 'integer|min:1|max:100',
                'debug_mode' => 'boolean',
                'auto_restart' => 'boolean'
            ]);

            $settings = [
                'telegram_use_simple_bot' => $request->boolean('use_simple_bot', false),
                'telegram_polling_interval' => $request->get('polling_interval', 1000),
                'telegram_rate_limit' => $request->get('rate_limit', 10),
                'telegram_debug_mode' => $request->boolean('debug_mode', false),
                'telegram_auto_restart' => $request->boolean('auto_restart', false)
            ];

            foreach ($settings as $key => $value) {
                $this->settingsService->set($key, $value);
            }

            Log::info('Telegram bot configuration updated by admin', [
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'settings' => $settings
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating telegram bot configuration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset system cache for telegram bot
     */
    public function resetCache(): JsonResponse
    {
        try {
            // Clear all Laravel caches
            Cache::flush();
            
            // Clear specific telegram bot caches
            Cache::forget('telegram_bot_status');
            Cache::forget('telegram_bot_info');
            Cache::forget('telegram_bot_statistics');
            Cache::forget('telegram_bot_configuration');
            Cache::forget('telegram_bot_users');
            Cache::forget('telegram_bot_messages');
            Cache::forget('telegram_webhook_info');
            
            // Clear config cache
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            
            // Clear route cache
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            
            // Clear view cache
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            
            // Clear compiled services
            \Illuminate\Support\Facades\Artisan::call('clear-compiled');
            
            // Clear optimization cache
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            
            // Log the cache reset
            Log::info('System cache reset for telegram bot by admin', [
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'timestamp' => now()
            ]);
            
            // Also create a test log entry to ensure logging is working
            $logFile = storage_path('logs/telegram-bot.log');
            $logDir = dirname($logFile);
            
            // Create log directory if it doesn't exist
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Write test log entry
            $testLogMessage = '[' . date('Y-m-d H:i:s') . '] System cache reset by admin: ' . auth()->user()->name . "\n";
            file_put_contents($logFile, $testLogMessage, FILE_APPEND | LOCK_EX);

            return response()->json([
                'success' => true,
                'message' => 'System cache reset successfully. All telegram bot and system caches have been cleared.',
                'data' => [
                    'cleared_caches' => [
                        'laravel_cache' => 'cleared',
                        'telegram_bot_caches' => 'cleared',
                        'config_cache' => 'cleared',
                        'route_cache' => 'cleared',
                        'view_cache' => 'cleared',
                        'compiled_services' => 'cleared',
                        'optimization_cache' => 'cleared'
                    ],
                    'log_directory_created' => !file_exists($logDir) ? 'created' : 'exists',
                    'test_log_written' => 'yes'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error resetting system cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error resetting cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if bot is running
     */
    private function isBotRunning(): bool
    {
        try {
            $pidFile = storage_path('telegram-bot.pid');
            
            if (!file_exists($pidFile)) {
                return false;
            }

            $pid = trim(file_get_contents($pidFile));
            
            if (empty($pid)) {
                return false;
            }

            // Check if process is still running
            $result = Process::run("ps -p {$pid}");
            
            return $result->successful();
            
        } catch (\Exception $e) {
            Log::error('Error checking bot status', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get detailed bot status
     */
    private function getBotStatus(): array
    {
        try {
            $isRunning = $this->isBotRunning();
            $pidFile = storage_path('telegram-bot.pid');
            $pid = null;
            $uptime = null;
            
            if ($isRunning && file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                
                // Get process info
                $result = Process::run("ps -p {$pid} -o pid,etime,pcpu,pmem");
                if ($result->successful()) {
                    $lines = explode("\n", trim($result->output()));
                    if (count($lines) > 1) {
                        $processInfo = preg_split('/\s+/', trim($lines[1]));
                        $uptime = $processInfo[1] ?? null;
                    }
                }
            }

            return [
                'is_running' => $isRunning,
                'status' => $isRunning ? 'running' : 'stopped',
                'pid' => $pid,
                'uptime' => $uptime,
                'last_checked' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Error getting bot status', ['error' => $e->getMessage()]);
            
            return [
                'is_running' => false,
                'status' => 'unknown',
                'pid' => null,
                'uptime' => null,
                'last_checked' => now()->toISOString(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get bot information
     */
    private function getBotInfo(): array
    {
        try {
            $token = config('services.telegram.bot_token');
            $webhookUrl = config('services.telegram.webhook_url');
            $useSimple = config('telegram.use_simple_bot', false);
            
            return [
                'is_configured' => !empty($token),
                'has_webhook' => !empty($webhookUrl),
                'use_simple_mode' => $useSimple,
                'token_configured' => !empty($token),
                'webhook_url' => $webhookUrl,
                'polling_interval' => $this->settingsService->get('telegram_polling_interval', 1000),
                'rate_limit' => $this->settingsService->get('telegram_rate_limit', 10),
                'debug_mode' => $this->settingsService->get('telegram_debug_mode', false),
                'auto_restart' => $this->settingsService->get('telegram_auto_restart', false)
            ];

        } catch (\Exception $e) {
            Log::error('Error getting bot info', ['error' => $e->getMessage()]);
            
            return [
                'is_configured' => false,
                'has_webhook' => false,
                'use_simple_mode' => false,
                'token_configured' => false,
                'webhook_url' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get bot statistics
     */
    private function getBotStatistics(): array
    {
        try {
            // Get statistics from the system
            $totalUsers = \App\Models\User::where('telegram_user_id', '!=', null)->count();
            $totalMessages = Cache::get('telegram_total_messages', 0);
            $messagesLast24h = Cache::get('telegram_messages_24h', 0);
            $activeUsers = Cache::get('telegram_active_users', 0);

            return [
                'total_users' => $totalUsers,
                'total_messages' => $totalMessages,
                'messages_last_24h' => $messagesLast24h,
                'active_users' => $activeUsers,
                'last_updated' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Error getting bot statistics', ['error' => $e->getMessage()]);
            
            return [
                'total_users' => 0,
                'total_messages' => 0,
                'messages_last_24h' => 0,
                'active_users' => 0,
                'last_updated' => now()->toISOString(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get bot configuration
     */
    private function getBotConfiguration(): array
    {
        try {
            return [
                'use_simple_bot' => $this->settingsService->get('telegram_use_simple_bot', false),
                'polling_interval' => $this->settingsService->get('telegram_polling_interval', 1000),
                'rate_limit' => $this->settingsService->get('telegram_rate_limit', 10),
                'debug_mode' => $this->settingsService->get('telegram_debug_mode', false),
                'auto_restart' => $this->settingsService->get('telegram_auto_restart', false),
                'max_consecutive_errors' => $this->settingsService->get('telegram_max_consecutive_errors', 5),
                'timeout' => $this->settingsService->get('telegram_timeout', 30)
            ];

        } catch (\Exception $e) {
            Log::error('Error getting bot configuration', ['error' => $e->getMessage()]);
            
            return [
                'use_simple_bot' => false,
                'polling_interval' => 1000,
                'rate_limit' => 10,
                'debug_mode' => false,
                'auto_restart' => false,
                'max_consecutive_errors' => 5,
                'timeout' => 30,
                'error' => $e->getMessage()
            ];
        }
    }
} 