<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use TelegramBot\Api\Types\Update;

class TelegramWebhookController extends Controller
{
    private TelegramBotService $telegramService;
    private SettingsService $settingsService;

    public function __construct(TelegramBotService $telegramService, SettingsService $settingsService)
    {
        $this->telegramService = $telegramService;
        $this->settingsService = $settingsService;
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function handle(Request $request): Response
    {
        try {
            // Validate webhook secret if configured
            if (!$this->validateWebhookSecret($request)) {
                Log::warning('Invalid webhook secret in Telegram webhook', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                return response('Unauthorized', 401);
            }

            // Get and validate update data
            $updateData = $request->all();
            if (empty($updateData)) {
                Log::warning('Empty update data received in Telegram webhook');
                return response('Bad Request', 400);
            }

            // Check rate limiting
            if ($this->isRateLimited($request)) {
                Log::warning('Rate limit exceeded for Telegram webhook', [
                    'ip' => $request->ip(),
                ]);
                return response('Rate Limited', 429);
            }

            // Log incoming webhook for debugging
            if ($this->settingsService->get('advanced.telegram_debug_webhook', false)) {
                Log::debug('Telegram webhook received', [
                    'update_id' => $updateData['update_id'] ?? 'unknown',
                    'data' => $updateData,
                ]);
            }

            // Setup bot and process update
            $this->telegramService->setupBot();
            
            // Create Update object from webhook data
            $update = new Update($updateData);
            
            // Process the update in a try-catch to handle individual update errors
            $this->processUpdateSafely($update);

            // Return success response
            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Error handling Telegram webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            // Return error but don't expose internal details
            return response('Internal Server Error', 500);
        }
    }

    /**
     * Health check endpoint for webhook
     */
    public function health(Request $request): Response
    {
        try {
            // Basic health checks
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'webhook_url_configured' => !empty(config('services.telegram.webhook_url')),
                'bot_token_configured' => !empty(config('services.telegram.bot_token')),
                'queue_healthy' => $this->checkQueueHealth(),
                'database_healthy' => $this->checkDatabaseHealth(),
                'memory_usage' => $this->getMemoryUsage(),
                'uptime' => $this->getUptime(),
            ];

            // Check specific components
            $allHealthy = $health['webhook_url_configured'] && 
                         $health['bot_token_configured'] && 
                         $health['queue_healthy'] && 
                         $health['database_healthy'];

            $statusCode = $allHealthy ? 200 : 503;
            $health['overall_status'] = $allHealthy ? 'healthy' : 'unhealthy';

            return response()->json($health, $statusCode);

        } catch (\Exception $e) {
            Log::error('Error in Telegram webhook health check', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'error' => 'Health check failed',
            ], 503);
        }
    }

    /**
     * Get webhook configuration and stats
     */
    public function status(Request $request): Response
    {
        try {
            $stats = [
                'webhook_configured' => !empty(config('services.telegram.webhook_url')),
                'webhook_url' => config('services.telegram.webhook_url'),
                'bot_token_configured' => !empty(config('services.telegram.bot_token')),
                'secret_configured' => !empty(config('services.telegram.webhook_secret')),
                'last_webhook_received' => Cache::get('telegram_last_webhook_time'),
                'webhook_count_today' => Cache::get('telegram_webhook_count_' . today()->format('Y-m-d'), 0),
                'error_count_today' => Cache::get('telegram_webhook_errors_' . today()->format('Y-m-d'), 0),
                'rate_limit_settings' => [
                    'enabled' => $this->settingsService->get('advanced.telegram_webhook_rate_limit', true),
                    'max_requests' => $this->settingsService->get('advanced.telegram_webhook_rate_limit_max', 100),
                    'window_seconds' => $this->settingsService->get('advanced.telegram_webhook_rate_limit_window', 60),
                ],
                'debug_mode' => $this->settingsService->get('advanced.telegram_debug_webhook', false),
            ];

            return response()->json($stats, 200);

        } catch (\Exception $e) {
            Log::error('Error getting Telegram webhook status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get webhook status',
            ], 500);
        }
    }

    /**
     * Validate webhook secret
     */
    private function validateWebhookSecret(Request $request): bool
    {
        $expectedSecret = config('services.telegram.webhook_secret');
        
        // If no secret is configured, skip validation
        if (empty($expectedSecret)) {
            return true;
        }

        $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        
        return hash_equals($expectedSecret, $providedSecret ?? '');
    }

    /**
     * Check if request is rate limited
     */
    private function isRateLimited(Request $request): bool
    {
        if (!$this->settingsService->get('advanced.telegram_webhook_rate_limit', true)) {
            return false;
        }

        $key = 'telegram_webhook_rate_limit_' . $request->ip();
        $maxRequests = $this->settingsService->get('advanced.telegram_webhook_rate_limit_max', 100);
        $windowSeconds = $this->settingsService->get('advanced.telegram_webhook_rate_limit_window', 60);

        $current = Cache::get($key, 0);
        
        if ($current >= $maxRequests) {
            return true;
        }

        Cache::put($key, $current + 1, $windowSeconds);
        return false;
    }

    /**
     * Process update with comprehensive error handling
     */
    private function processUpdateSafely(Update $update): void
    {
        try {
            // Update webhook statistics
            $this->updateWebhookStats();

            // Process the update
            $reflection = new \ReflectionClass($this->telegramService);
            $method = $reflection->getMethod('processUpdate');
            $method->setAccessible(true);
            $method->invoke($this->telegramService, $update);

        } catch (\Exception $e) {
            // Log error but don't re-throw to avoid webhook failures
            Log::error('Error processing Telegram update in webhook', [
                'update_id' => $update->getUpdateId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update error statistics
            $this->updateErrorStats();

            // Try to send error notification to admin if configured
            $this->notifyAdminOfError($update, $e);
        }
    }

    /**
     * Update webhook statistics
     */
    private function updateWebhookStats(): void
    {
        $today = today()->format('Y-m-d');
        $countKey = "telegram_webhook_count_{$today}";
        $timeKey = 'telegram_last_webhook_time';

        Cache::increment($countKey, 1);
        Cache::put($countKey, Cache::get($countKey, 0), 86400); // Keep for 24 hours
        Cache::put($timeKey, now()->toISOString(), 86400);
    }

    /**
     * Update error statistics
     */
    private function updateErrorStats(): void
    {
        $today = today()->format('Y-m-d');
        $errorKey = "telegram_webhook_errors_{$today}";

        Cache::increment($errorKey, 1);
        Cache::put($errorKey, Cache::get($errorKey, 0), 86400); // Keep for 24 hours
    }

    /**
     * Notify admin of webhook errors
     */
    private function notifyAdminOfError(Update $update, \Exception $e): void
    {
        try {
            $adminChatId = $this->settingsService->get('advanced.telegram_admin_chat_id');
            
            if (!$adminChatId) {
                return;
            }

            $message = "🚨 *Telegram Webhook Error*\n\n";
            $message .= "📅 Time: " . now()->format('Y-m-d H:i:s') . "\n";
            $message .= "🆔 Update ID: " . $update->getUpdateId() . "\n";
            $message .= "❌ Error: " . substr($e->getMessage(), 0, 200) . "\n";
            $message .= "🔍 Type: " . get_class($e);

            // Try to send notification (but don't fail if it doesn't work)
            $this->telegramService->sendMessage($adminChatId, $message, 'Markdown');

        } catch (\Exception $notificationError) {
            Log::warning('Failed to send admin notification for webhook error', [
                'notification_error' => $notificationError->getMessage(),
                'original_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check queue health
     */
    private function checkQueueHealth(): bool
    {
        try {
            // Simple queue health check - try to dispatch a test job or check queue status
            $queueSize = Cache::get('queue_size', 0);
            return $queueSize < 1000; // Consider unhealthy if queue is too large
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth(): bool
    {
        try {
            \DB::select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get memory usage information
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
    }

    /**
     * Get system uptime
     */
    private function getUptime(): ?string
    {
        try {
            if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                $uptime = explode(' ', $uptime)[0];
                return gmdate('H:i:s', $uptime);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
} 