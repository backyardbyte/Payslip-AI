<?php

namespace App\Http\Controllers;

use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle Telegram webhook updates
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Verify webhook secret if configured
            $webhookSecret = config('services.telegram.webhook_secret');
            if ($webhookSecret) {
                $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
                if ($providedSecret !== $webhookSecret) {
                    Log::warning('Telegram webhook unauthorized access attempt', [
                        'ip' => $request->ip(),
                        'provided_secret' => $providedSecret
                    ]);
                    return response()->json(['error' => 'Unauthorized'], 401);
                }
            }

            // Get update data
            $updateData = $request->all();

            // Log the update for debugging
            Log::info('Telegram webhook update received', [
                'update_id' => $updateData['update_id'] ?? null,
                'message_id' => $updateData['message']['message_id'] ?? null,
                'chat_id' => $updateData['message']['chat']['id'] ?? null,
            ]);

            // Process the update
            $botService = new TelegramBotService();
            $botService->setupBot();
            $botService->processWebhookUpdate($updateData);

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Health check endpoint for Telegram webhook
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'bot_configured' => !empty(config('services.telegram.bot_token')),
        ]);
    }
} 