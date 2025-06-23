<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppBotService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle WhatsApp webhook verification
     */
    public function verify(Request $request)
    {
        try {
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            $botService = new WhatsAppBotService();
            $verificationResult = $botService->verifyWebhook($mode, $token, $challenge);

            if ($verificationResult) {
                Log::info('WhatsApp webhook verified successfully');
                return response($verificationResult, 200);
            }

            Log::warning('WhatsApp webhook verification failed', [
                'mode' => $mode,
                'token' => $token,
                'ip' => $request->ip()
            ]);

            return response('Forbidden', 403);

        } catch (\Exception $e) {
            Log::error('WhatsApp webhook verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * Handle WhatsApp webhook updates
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Get update data
            $updateData = $request->all();

            // Log the update for debugging
            Log::info('WhatsApp webhook update received', [
                'object' => $updateData['object'] ?? null,
                'entry_count' => count($updateData['entry'] ?? [])
            ]);

            // Verify this is a WhatsApp Business message
            if (($updateData['object'] ?? null) !== 'whatsapp_business_account') {
                Log::warning('Invalid WhatsApp webhook object type', ['object' => $updateData['object'] ?? null]);
                return response()->json(['status' => 'ok']);
            }

            // Process the update
            $botService = new WhatsAppBotService();
            $botService->setupBot();
            $botService->processWebhookUpdate($updateData);

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Health check endpoint for WhatsApp webhook
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'bot_configured' => !empty(config('services.whatsapp.access_token')) && !empty(config('services.whatsapp.phone_number_id')),
        ]);
    }
} 